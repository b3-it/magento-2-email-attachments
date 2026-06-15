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

/**
 * Replaces Magento\Framework\Mail\EmailMessage so TransportFactory plugin
 * can identify it via instanceof and dispatch MailEvent for attachments.
 * addCc() and addBcc() are inherited from Magento\Framework\Mail\Message (Symfony-based).
 */
class EmailMessage extends MagentoEmailMessage
{
}
