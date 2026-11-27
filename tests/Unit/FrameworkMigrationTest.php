<?php

namespace Tests\Unit;

use App\Http\Middleware\TrustProxies;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FrameworkMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        Container::setInstance($container);
        $container->instance('config', new Repository(['trustedproxy' => ['proxies' => []]]));
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testUntrustedForwardedHeadersCannotChangeClientOrScheme(): void
    {
        $request = Request::create('http://example.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.0.2.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.4',
            'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'evil.test',
        ]);
        (new TrustProxies())->handle($request, fn () => null);
        self::assertSame('192.0.2.5', $request->ip());
        self::assertFalse($request->isSecure());
        self::assertSame('example.test', $request->host());
    }

    public function testExplicitCidrTrustIsResetForTheNextRequest(): void
    {
        config()->set('trustedproxy.proxies', ['192.0.2.0/24']);
        $request = Request::create('http://example.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.0.2.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.4',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        (new TrustProxies())->handle($request, fn () => null);
        self::assertSame('198.51.100.4', $request->ip());
        self::assertTrue($request->isSecure());
        config()->set('trustedproxy.proxies', []);
        (new TrustProxies())->handle($request, fn () => null);
        self::assertSame('192.0.2.5', $request->ip());
        self::assertFalse($request->isSecure());
    }

    public function testImageFourDecodesUploadedFileAndProducesPng(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rise-image-');
        try {
            $image = imagecreatetruecolor(2, 3);
            imagepng($image, $path);
            $upload = new UploadedFile($path, 'test.png', 'image/png', null, true);
            $encoded = (string) ImageManager::usingDriver(Driver::class)
                ->decodeSplFileInfo($upload)->encodeUsingFileExtension('png');
            self::assertSame([2, 3], array_slice(getimagesizefromstring($encoded), 0, 2));
        } finally {
            unlink($path);
        }
    }
    public function testUploadRejectsSvgBeforeWriting(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rise-svg-');
        try {
            file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
            $upload = new \Illuminate\Http\UploadedFile($path, 'test.svg', 'image/svg+xml', null, true);
            $this->expectException(\InvalidArgumentException::class);
            \App\Helper\UploadHelper::upload($upload, 'test');
        } finally {
            unlink($path);
        }
    }

    public function testCarbonCalendarWindowRetainsIntegerDaySemanticsAcrossDst(): void
    {
        $start = \Carbon\Carbon::parse('2026-03-28 00:00:00', 'Europe/Berlin');
        $end = \Carbon\Carbon::parse('2026-03-30 23:59:59', 'Europe/Berlin');
        self::assertSame(2, (int) $start->diffInDays($end, true));
        $shift = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay(), true) + 1;
        self::assertSame(3, $shift);
        self::assertSame('2026-03-25 00:00:00', $start->copy()->subDays($shift)->format('Y-m-d H:i:s'));
    }

}
