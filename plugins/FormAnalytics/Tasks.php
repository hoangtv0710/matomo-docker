<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FormAnalytics;

use Piwik\Plugins\FormAnalytics\Dao\LogForm;
use Psr\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{

    /**
     * @var LogForm
     */
    private $logForm;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LogForm $logForm, LoggerInterface $logger)
    {
        $this->logForm = $logForm;
        $this->logger = $logger;
    }

    public function schedule()
    {
        $this->monthly('removeDeletedFormLogEntries');
    }

    /**
     * To test execute the following command:
     * `./console core:run-scheduled-tasks "Piwik\Plugins\FormAnalytics\Tasks.removeDeletedFormLogEntries"`
     *
     * @throws \Exception
     */
    public function removeDeletedFormLogEntries()
    {
        $loopMax = 500;
        $index = 0;
        $numDeleted = 0;

        do {
            if ($index > $loopMax) {
                $this->logger->info(sprintf('Deleted %s log forms so far and stopping because of too many loops. Next time will delete again.', $numDeleted));

                return; // safety loop... delete max 25M rows per table in one cronjob (500 loops * 50k max entries)
            }
            $index++;

            $logForms = $this->logForm->findDeletedLogFormIds($maxEntries = 50000);
            $numDeleted += count($logForms);
            $this->logForm->deleteLogEntriesForRemovedForms($logForms);
        } while (!empty($logForms));

        $this->logger->info(sprintf('Deleted %s log forms', $numDeleted));
    }
}
