<?php

namespace App\Service;

use App\Entity\ContactMessage;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactReplyService
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private string $appName = 'Idioma Club'
    ) {}

    /**
     * Send a reply to a contact message
     */
    public function sendReply(ContactMessage $message, string $replyContent): void
    {
        $email = (new Email())
            ->from('noreply@idioma-club.com')
            ->to($message->getEmail())
            ->subject('Re: ' . $message->getSubject())
            ->html($this->renderReplyTemplate($message, $replyContent));

        $this->mailer->send($email);

        // Update message status
        $message->setStatus('responded');
        $message->setRespondedAt(new DateTime());
        $this->entityManager->flush();
    }

    /**
     * Render reply email template
     */
    private function renderReplyTemplate(ContactMessage $message, string $replyContent): string
    {
        $originalMessage = htmlspecialchars($message->getMessage());
        $replyContent = htmlspecialchars($replyContent);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
        .reply-box { background-color: white; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        .original-box { background-color: #f0f0f0; padding: 15px; border-left: 4px solid #999; margin: 20px 0; font-size: 12px; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Réponse à votre message</h1>
        </div>
        <div class="content">
            <p>Bonjour {$message->getName()},</p>
            
            <div class="reply-box">
                <strong>Réponse :</strong><br>
                {$replyContent}
            </div>

            <div class="original-box">
                <strong>Votre message original :</strong><br>
                <strong>Sujet :</strong> {$message->getSubject()}<br>
                <strong>Message :</strong><br>
                {$originalMessage}
            </div>

            <p>Cordialement,<br>L'équipe {$this->appName}</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$this->appName}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

