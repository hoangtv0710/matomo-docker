<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\PerformanceAudit\Exceptions;

require PIWIK_INCLUDE_PATH . '/plugins/PerformanceAudit/vendor/autoload.php';

use Exception;

class AuditFailedMethodNotAllowedException extends Exception
{
    /**
     * AuditFailedMethodNotAllowedException constructor.
     *
     * @param string $url
     * @param null|string $output
     */
    public function __construct(string $url, $output = null)
    {
        parent::__construct('Audit of ' . $url . ' failed due to not allowed method: ' . $output);
    }
}
