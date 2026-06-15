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

/**
 * Transport wrapper so TransportFactory plugin can detect it via instanceof
 * and dispatch MailEvent. Actual sending is handled by the parent (Symfony-based)
 * or intercepted by the smtp module's plugin.
 */
class Transport extends \Magento\Email\Model\Transport
{
}
