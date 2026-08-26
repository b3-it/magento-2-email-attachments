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

/**
 * Dispatched by TransportFactory plugin to attach PDFs and files to order emails.
 */
class MailEvent
{
    const MIME_TYPES = [
        'txt'  => 'text/plain',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/msword',
    ];

    /**
     * Pending attachments: each entry is ['data' => string, 'filename' => string, 'mimeType' => string]
     *
     * @var array
     */
    private array $parts = [];

    public function __construct(
        private readonly Mail $mail,
        private readonly Data $dataHelper,
        private readonly Filesystem $filesystem,
        private readonly ObjectManagerInterface $objectManager,
        private readonly SessionManagerInterface $coreSession
    ) {}

    /**
     * @throws \Zend_Pdf_Exception
     */
    public function dispatch(EmailMessage $message): void
    {
        $templateVars = $this->mail->getTemplateVars();
        if (!$templateVars) {
            return;
        }

        /** @var Store|null $store */
        $store = $templateVars['store'] ?? null;
        $storeId = $store ? $store->getId() : null;

        if (!$this->dataHelper->isEnabled($storeId)) {
            return;
        }

        if ($emailType = $this->getEmailType($templateVars)) {
            /** @var Order|Invoice|Shipment|Creditmemo $obj */
            $obj = $templateVars[$emailType];

            $hasAttachment = false;

            if ($this->dataHelper->isEnabledAttachPdf($storeId)
                && in_array($emailType, $this->dataHelper->getAttachPdf($storeId), true)
            ) {
                $this->setPdfAttachment($emailType, $obj);
                $hasAttachment = true;
            }

            if ($this->dataHelper->getTacFile($storeId)
                && in_array($emailType, $this->dataHelper->getAttachTac($storeId), true)
            ) {
                $this->setTACAttachment($storeId);
                $hasAttachment = true;
            }

            if ($hasAttachment) {
                $this->applyAttachments($message);
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

    private function getEmailType(array|\ArrayAccess $templateVars): string|false
    {
        foreach (['invoice', 'shipment', 'creditmemo', 'order'] as $emailType) {
            if (isset($templateVars[$emailType])) {
                return $emailType;
            }
        }
        return false;
    }

    /**
     * @throws \Zend_Pdf_Exception
     */
    private function setPdfAttachment(string $emailType, Order|Invoice|Shipment|Creditmemo $obj): void
    {
        $pdfModel = 'Magento\Sales\Model\Order\Pdf\\' . ucfirst($emailType);
        /** @var \Zend_Pdf $pdf */
        $pdf = $this->objectManager->create($pdfModel)->getPdf([$obj]);

        $this->parts[] = [
            'data'     => $pdf->render(),
            'filename' => $emailType . $obj->getIncrementId() . '.pdf',
            'mimeType' => 'application/pdf',
        ];
    }

    private function setTACAttachment(int|string|null $storeId = null): void
    {
        [$content, $ext, $mimeType] = $this->getTacFile($storeId);

        $this->parts[] = [
            'data'     => $content,
            'filename' => (string)__('terms_and_conditions') . '.' . $ext,
            'mimeType' => $mimeType,
        ];
    }

    private function applyAttachments(EmailMessage $message): void
    {
        foreach ($this->parts as $part) {
            $message->addAttachment($part['data'], $part['filename'], $part['mimeType']);
        }
    }

    private function getTacFile(int|string|null $storeId = null): array
    {
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $tacPath = $this->dataHelper->getTacFile($storeId);
        $filePath = $mediaDirectory->getAbsolutePath('mageplaza/email_attachments/' . $tacPath);
        $content = file_get_contents($filePath);
        $ext = (string)substr($filePath, strrpos($filePath, '.') + 1);

        return [$content, $ext, self::MIME_TYPES[$ext]];
    }
}
