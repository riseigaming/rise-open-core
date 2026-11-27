<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Services\Cms\CmsMailBranding;

class CmsEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public const PURPOSE_REGISTRATION = 'registration';

    public const PURPOSE_EMAIL_CHANGE = 'email_change';


    /**
     * Create a new message instance.
     *
     * @return void
     */

    protected $user;
    protected $token;
    protected ?string $mailLocale;
    protected string $purpose;

    public function __construct($user, $token, ?string $locale = null, string $purpose = self::PURPOSE_REGISTRATION)
    {
         $this->user = $user;
         $this->token = $token;
         $this->mailLocale = $locale;
         $this->purpose = $purpose === self::PURPOSE_EMAIL_CHANGE
            ? self::PURPOSE_EMAIL_CHANGE
            : self::PURPOSE_REGISTRATION;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $locale = $this->normalizeLocale($this->mailLocale ?: ($this->user->language ?: app()->getLocale()));
        $brand = app(CmsMailBranding::class)->current();
        $copy = $this->copyForLocale($locale, $this->purpose);
        $copy = $this->applyBrandName($copy, (string) $brand['name']);
        $activationUrl = route("cms.front.verifyEmail", ["verifyToken" => $this->token]);

        return $this
            ->subject($copy['subject'])
            ->view('cms.emails.email-validation', [
                "user" => $this->user,
                "token" => $this->token,
                "activationUrl" => $activationUrl,
                "copy" => $copy,
                "locale" => $locale,
                "brand" => $brand,
            ]);
    }

    private function applyBrandName(array $copy, string $brandName): array
    {
        return array_map(
            static fn ($value) => is_string($value) ? str_replace(':brand', $brandName, $value) : $value,
            $copy
        );
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        return in_array($locale, ['tr', 'en', 'es'], true) ? $locale : 'en';
    }

    /**
     * @return array{subject: string, preheader: string, eyebrow: string, greeting: string, title: string, body: string, cta: string, fallback: string, security_title: string, security_body: string, footer: string}
     */
    private function copyForLocale(string $locale, string $purpose): array
    {
        $registrationCopy = match ($locale) {
            'en' => [
                'subject' => 'Verify your email address | :brand',
                'preheader' => 'One last step to secure your :brand account.',
                'eyebrow' => 'Account security',
                'greeting' => 'Hello',
                'title' => 'Verify your email address',
                'body' => 'Confirm this address to secure your account and make sure important account notifications reach you.',
                'cta' => 'Verify email',
                'fallback' => 'If the button does not work, copy and paste this link into your browser:',
                'security_title' => 'Didn’t request this?',
                'security_body' => 'You can safely ignore this email. No changes will be made to your account.',
                'footer' => 'This is an automated account security message from :brand.',
            ],
            'es' => [
                'subject' => 'Verifica tu correo electrónico | :brand',
                'preheader' => 'Un último paso para proteger tu cuenta de :brand.',
                'eyebrow' => 'Seguridad de la cuenta',
                'greeting' => 'Hola',
                'title' => 'Verifica tu correo electrónico',
                'body' => 'Confirma esta dirección para proteger tu cuenta y recibir correctamente los avisos importantes.',
                'cta' => 'Verificar correo',
                'fallback' => 'Si el botón no funciona, copia y pega este enlace en tu navegador:',
                'security_title' => '¿No solicitaste esto?',
                'security_body' => 'Puedes ignorar este correo de forma segura. No se realizará ningún cambio en tu cuenta.',
                'footer' => 'Este es un mensaje automático de seguridad de la cuenta enviado por :brand.',
            ],
            default => [
                'subject' => 'E-posta adresini doğrula | :brand',
                'preheader' => ':brand hesabını güvenceye almak için son bir adım.',
                'eyebrow' => 'Hesap güvenliği',
                'greeting' => 'Merhaba',
                'title' => 'E-posta adresini doğrula',
                'body' => 'Hesabını güvenceye almak ve önemli hesap bildirimlerini doğru adrese ulaştırmak için bu e-posta adresini onayla.',
                'cta' => 'E-postayı doğrula',
                'fallback' => 'Buton çalışmazsa bu bağlantıyı kopyalayıp tarayıcına yapıştır:',
                'security_title' => 'Bu işlemi sen yapmadın mı?',
                'security_body' => 'Bu e-postayı güvenle yok sayabilirsin. Hesabında herhangi bir değişiklik yapılmaz.',
                'footer' => 'Bu, :brand tarafından gönderilen otomatik bir hesap güvenliği mesajıdır.',
            ],
        };

        if ($purpose !== self::PURPOSE_EMAIL_CHANGE) {
            return $registrationCopy;
        }

        return array_merge($registrationCopy, match ($locale) {
            'en' => [
                'subject' => 'Confirm your email change | :brand',
                'preheader' => 'Confirm the new email address for your :brand account.',
                'eyebrow' => 'Email change',
                'title' => 'Confirm your new email address',
                'body' => 'A request was made to replace the email address on your :brand account with this one. Confirm the change to connect this address to your account.',
                'cta' => 'Confirm change',
                'security_title' => 'Didn’t request this change?',
                'security_body' => 'Do not use this link. Sign in to review the email address on your profile and contact live support if you need help.',
                'footer' => 'This is an automated email change confirmation from :brand.',
            ],
            'es' => [
                'subject' => 'Confirma el cambio de correo | :brand',
                'preheader' => 'Confirma la nueva dirección de correo de tu cuenta de :brand.',
                'eyebrow' => 'Cambio de correo',
                'title' => 'Confirma tu nueva dirección',
                'body' => 'Se solicitó reemplazar el correo de tu cuenta de :brand por esta dirección. Confirma el cambio para vincularla a tu cuenta.',
                'cta' => 'Confirmar cambio',
                'security_title' => '¿No solicitaste este cambio?',
                'security_body' => 'No utilices este enlace. Inicia sesión para revisar el correo de tu perfil y contacta con el soporte en vivo si necesitas ayuda.',
                'footer' => 'Esta es una confirmación automática de cambio de correo enviada por :brand.',
            ],
            default => [
                'subject' => 'E-posta değişikliğini onayla | :brand',
                'preheader' => ':brand hesabındaki yeni e-posta adresini onayla.',
                'eyebrow' => 'E-posta değişikliği',
                'title' => 'Yeni e-posta adresini onayla',
                'body' => ':brand hesabındaki e-posta adresinin bu adresle değiştirilmesi istendi. Bu adresi hesabına bağlamak için değişikliği onayla.',
                'cta' => 'Değişikliği onayla',
                'security_title' => 'Bu değişikliği sen istemedin mi?',
                'security_body' => 'Bu bağlantıyı kullanma. Profilindeki e-posta adresini kontrol etmek için hesabına giriş yap ve gerekirse canlı desteğe ulaş.',
                'footer' => 'Bu, :brand tarafından gönderilen otomatik bir e-posta değişikliği onayıdır.',
            ],
        });
    }
}
