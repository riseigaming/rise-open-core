<?php
namespace Tests\Unit;

use App\Http\Controllers\Cms\Front\ProfileController;
use App\Models\CmsUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class B1ProfileBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    public function testGuestCannotChangePassword(): void
    {
        Auth::guard('cms')->forgetUser();
        try {
            (new ProfileController())->update(Request::create('/cms/update-profile', 'POST'));
            self::fail('Guest request accepted.');
        } catch (HttpException $e) {
            self::assertSame(401, $e->getStatusCode());
        }
    }

    public function testAuthenticatedRequestCannotMutateAnotherField(): void
    {
        Auth::guard('cms')->setUser((new CmsUser())->forceFill(['id' => 123]));
        try {
            (new ProfileController())->update(Request::create('/cms/update-profile', 'POST', [
                'balance' => '90000', 'currentPassword' => 'old', 'new_password' => 'new', 'password_confirm' => 'new',
            ]));
            self::fail('Unsupported profile mutation accepted.');
        } catch (HttpException $e) {
            self::assertSame(422, $e->getStatusCode());
        }
    }
}
