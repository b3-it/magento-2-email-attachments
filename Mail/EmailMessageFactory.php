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

namespace Mageplaza\EmailAttachments\Mail;

use Magento\Framework\ObjectManagerInterface;

class EmailMessageFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly string $instanceName = EmailMessage::class
    ) {}

    public function create(array $data = []): EmailMessage
    {
        return $this->objectManager->create($this->instanceName, $data);
    }
}
