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
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_EmailAttachments
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\EmailAttachments\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\Store;
use Mageplaza\EmailAttachments\Helper\Data;
use Mageplaza\EmailAttachments\Mail\EmailMessage;
use Laminas\Mail\Message;
use Laminas\Mime\Mime;
use Laminas\Mime\Part;
use Laminas\Mime\Mime as Zend_Mime;
use Laminas\Mime\Decode as Zend_Mime_Decode;
use Zend_Pdf;
use Zend_Pdf_Exception;

/**
 * Class MailEvent
 * @package Mageplaza\EmailAttachments\Model
 */
class MailEvent
{
    /**
     * @var array
     */
    const MIME_TYPES = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/msword',
    ];

    /**
     * @var array
     */
    private $parts = [];

    /**
     * @var Mail
     */
    private $mail;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var SessionManagerInterface
     */
    private $coreSession;

    /**
     * MailEvent constructor.
     *
     * @param Mail $mail
     * @param Data $dataHelper
     * @param Filesystem $filesystem
     * @param ObjectManagerInterface $objectManager
     * @param SessionManagerInterface $coreSession
     */
    public function __construct(
        Mail $mail,
        Data $dataHelper,
        Filesystem $filesystem,
        ObjectManagerInterface $objectManager,
        SessionManagerInterface $coreSession
    ) {
        $this->mail = $mail;
        $this->dataHelper = $dataHelper;
        $this->filesystem = $filesystem;
        $this->objectManager = $objectManager;
        $this->coreSession = $coreSession;
    }

    /**
     * @param EmailMessage $message
     *
     * @throws Zend_Pdf_Exception
     */
    public function dispatch(EmailMessage $message)
    {
        $templateVars = $this->mail->getTemplateVars();
        if (!$templateVars) {
            return;
        }
        /** @var Store|null $store */
        $store = isset($templateVars['store']) ? $templateVars['store'] : null;
        $storeId = $store ? $store->getId() : null;

        if (!$this->dataHelper->isEnabled($storeId)) {
            return;
        }

        if ($emailType = $this->getEmailType($templateVars)) {
            /** @var Order|Invoice|Shipment|Creditmemo $obj */
            $obj = $templateVars[$emailType];

            $attachmentPDF = null;
            if ($this->dataHelper->checkPdfInvoiceIsEnable()) {
                // @phpstan-ignore-next-line (Magento session magic getter via __call)
                $attachmentPDF = $this->coreSession->getAttachPdf();
                if (is_object($attachmentPDF)) {
                    $this->parts[] = $attachmentPDF;
                }
            }

            if ($this->dataHelper->isEnabledAttachPdf($storeId)
                && in_array($emailType, $this->dataHelper->getAttachPdf($storeId), true)) {
                $this->setPdfAttachment($emailType, $obj, $attachmentPDF);
                $attachmentPDF = true;
            }

            if ($this->dataHelper->getTacFile($storeId)
                && in_array($emailType, $this->dataHelper->getAttachTac($storeId), true)) {
                $this->setTACAttachment($storeId);
                $attachmentPDF = true;
            }

            if ($this->dataHelper->versionCompare('2.2.9') && $attachmentPDF) {
                $this->setBodyAttachment($message);
                $this->parts = [];
            }

            foreach ($this->dataHelper->getCcTo($storeId) as $email) {
                $message->addCc(trim($email));
            }

            foreach ($this->dataHelper->getBccTo($storeId) as $email) {
                $message->addBcc(trim($email));
            }
        }

        $this->mail->setTemplateVars([]);
    }

    /**
     * @param $templateVars
     *
     * @return bool|string
     */
    private function getEmailType($templateVars)
    {
        $emailTypes = ['invoice', 'shipment', 'creditmemo', 'order'];

        foreach ($emailTypes as $emailType) {
            if (isset($templateVars[$emailType])) {
                return $emailType;
            }
        }

        return false;
    }

    /**
     * @param $emailType
     * @param $obj
     * @param mixed $attachmentPDF
     *
     * @throws Zend_Pdf_Exception
     */
    private function setPdfAttachment($emailType, $obj, $attachmentPDF = null)
    {
        if (is_object($attachmentPDF)) {
            return;
        }

        $pdfModel = 'Magento\Sales\Model\Order\Pdf\\' . ucfirst($emailType);
        /** @var Zend_Pdf $pdf */
        $pdf = $this->objectManager->create($pdfModel)->getPdf([$obj]);

        $attachment = new Part($pdf->render());
        $attachment->type = 'application/pdf';
        $attachment->encoding = Zend_Mime::ENCODING_BASE64;
        $attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
        $attachment->filename = $emailType . $obj->getIncrementId() . '.pdf';

        $this->parts[] = $attachment;
    }

    /**
     * @param null $storeId
     */
    private function setTACAttachment($storeId = null)
    {
        [$content, $ext, $mimeType] = $this->getTacFile($storeId);

        $attachment = new Part($content);
        $attachment->type = $mimeType;
        $attachment->encoding = Zend_Mime::ENCODING_BASE64;
        $attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
        $attachment->filename = __('terms_and_conditions') . '.' . $ext;

        $this->parts[] = $attachment;
    }

    /**
     * @param EmailMessage $message
     */
    private function setBodyAttachment(EmailMessage $message)
    {
        $body = Message::fromString($message->getRawMessage())->getBody();
        if ($this->dataHelper->versionCompare('2.3.3')) {
            $body = Zend_Mime_Decode::decodeQuotedPrintable($body);
        }

        $part = new Part($body);
        $part->setCharset('utf-8');
        $part->setEncoding(Mime::ENCODING_BASE64);
        if ($this->dataHelper->versionCompare('2.3.3')) {
            $part->setEncoding(Mime::ENCODING_QUOTEDPRINTABLE);
            $part->setDisposition(Mime::DISPOSITION_INLINE);
        }
        $part->setType(Mime::TYPE_HTML);
        array_unshift($this->parts, $part);

        $bodyPart = new \Laminas\Mime\Message();
        $bodyPart->setParts($this->parts);
        $message->setBody($bodyPart);
    }

    /**
     * @param null $storeId
     *
     * @return array
     */
    private function getTacFile($storeId = null)
    {
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $tacPath = $this->dataHelper->getTacFile($storeId);
        $filePath = $mediaDirectory->getAbsolutePath('mageplaza/email_attachments/' . $tacPath);
        $content = file_get_contents($filePath);
        $ext = (string)substr($filePath, strrpos($filePath, '.') + 1);

        return [$content, $ext, self::MIME_TYPES[$ext]];
    }
}
