<?php

namespace App\Mail;

use App\Models\CmsUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Services\Cms\CmsMailBranding;

class CmsPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    private CmsUser $user;

    private string $token;

    private string $mailLocale;

    public function __construct(CmsUser $user, string $token, ?string $locale = null)
    {
        $this->user = $user;
        $this->token = $token;
        $this->mailLocale = $this->normalizeLocale($locale ?: $user->language ?: app()->getLocale());
    }

    public function build()
    {
        $brand = app(CmsMailBranding::class)->current();
        $copy = $this->copyForLocale($this->mailLocale);
        $copy = array_map(
            static fn ($value) => is_string($value) ? str_replace(':brand', (string) $brand['name'], $value) : $value,
            $copy
        );
        $resetUrl = route('cms.password.reset.form', [
            'token' => $this->token,
            'email' => $this->user->email,
            'lang' => $this->mailLocale,
        ]);

        return $this
            ->subject($copy['subject'])
            ->view('cms.emails.email-validation', [
                'user' => $this->user,
                'token' => $this->token,
                'activationUrl' => $resetUrl,
                'copy' => $copy,
                'locale' => $this->mailLocale,
                'brand' => $brand,
            ]);
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        return in_array($locale, ['tr', 'en', 'es'], true) ? $locale : 'en';
    }

    private function copyForLocale(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'subject' => 'Reset your password | :brand',
                'preheader' => 'Use this secure link to create a new :brand password.',
                'eyebrow' => 'Account recovery',
                'greeting' => 'Hello',
                'title' => 'Create a new password',
                'body' => 'We received a request to reset the password for your :brand account. This secure link is valid for 60 minutes.',
                'cta' => 'Reset password',
                'fallback' => 'If the button does not work, copy and paste this link into your browser:',
                'security_title' => 'Didn’t request a password reset?',
                'security_body' => 'You can safely ignore this email. Your current password will remain unchanged.',
                'footer' => 'This is an automated account recovery message from :brand.',
            ],
            'es' => [
                'subject' => 'Restablece tu contraseña | :brand',
                'preheader' => 'Utiliza este enlace seguro para crear una nueva contraseña de :brand.',
                'eyebrow' => 'Recuperación de cuenta',
                'greeting' => 'Hola',
                'title' => 'Crea una nueva contraseña',
                'body' => 'Recibimos una solicitud para restablecer la contraseña de tu cuenta de :brand. Este enlace seguro es válido durante 60 minutos.',
                'cta' => 'Restablecer contraseña',
                'fallback' => 'Si el botón no funciona, copia y pega este enlace en tu navegador:',
                'security_title' => '¿No solicitaste restablecerla?',
                'security_body' => 'Puedes ignorar este correo de forma segura. Tu contraseña actual no cambiará.',
                'footer' => 'Este es un mensaje automático de recuperación de cuenta enviado por :brand.',
            ],
            default => [
                'subject' => 'Şifreni yenile | :brand',
                'preheader' => 'Yeni :brand şifreni oluşturmak için güvenli bağlantıyı kullan.',
                'eyebrow' => 'Hesap kurtarma',
                'greeting' => 'Merhaba',
                'title' => 'Yeni şifreni oluştur',
                'body' => ':brand hesabının şifresini yenilemek için bir istek aldık. Bu güvenli bağlantı 60 dakika geçerlidir.',
                'cta' => 'Şifremi yenile',
                'fallback' => 'Buton çalışmazsa bu bağlantıyı kopyalayıp tarayıcına yapıştır:',
                'security_title' => 'Bu isteği sen yapmadın mı?',
                'security_body' => 'Bu e-postayı güvenle yok sayabilirsin. Mevcut şifren değişmez.',
                'footer' => 'Bu, :brand tarafından gönderilen otomatik bir hesap kurtarma mesajıdır.',
            ],
        };
    }
}
