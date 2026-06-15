<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * @category    Mageplaza
 * @package     Mageplaza_EmailAttachments
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

declare(strict_types=1);

namespace Mageplaza\EmailAttachments\Mail;

use Magento\Framework\Mail\EmailMessage as MagentoEmailMessage;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;

/**
 * Replaces Magento\Framework\Mail\EmailMessage so TransportFactory plugin
 * can identify it via instanceof and dispatch MailEvent for attachments.
 * addCc() and addBcc() are inherited from Magento\Framework\Mail\Message (Symfony-based).
 */
class EmailMessage extends MagentoEmailMessage
{
    /**
     * Adds a binary attachment to the Symfony MIME message.
     * Wraps the existing body in a MixedPart if needed.
     */
    public function addAttachment(string $data, string $filename, string $mimeType): void
    {
        $dataPart = new DataPart($data, $filename, $mimeType);
        $currentBody = $this->symfonyMessage->getBody();

        if ($currentBody instanceof MixedPart) {
            $parts = array_merge($currentBody->getParts(), [$dataPart]);
            $this->symfonyMessage->setBody(new MixedPart(...$parts));
        } else {
            $this->symfonyMessage->setBody(new MixedPart($currentBody, $dataPart));
        }
    }
}
