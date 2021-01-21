<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\HeatmapSessionRecording\Commands;

use Piwik\API\Request;
use Piwik\Plugin\ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveHeatmapScreenshot extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('heatmapsessionrecording:remove-heatmap-screenshot');
        $this->setDescription('Removes a saved heatmap screenshot which can be useful if you want Matomo to re-take this screenshot');
        $this->addOption('idsite', null, InputOption::VALUE_REQUIRED, 'The ID of the site the heatmap belongs to');
        $this->addOption('idheatmap', null, InputOption::VALUE_REQUIRED, 'The ID of the heatamp');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkAllRequiredOptionsAreNotEmpty($input);
        $idSite = $input->getOption('idsite');
        $idHeatmap = $input->getOption('idheatmap');
        
        $success = Request::processRequest('HeatmapSessionRecording.deleteHeatmapScreenshot', array(
            'idSite' => $idSite,
            'idSiteHsr' => $idHeatmap
        ));

        if ($success) {
            $output->writeln('<info>Screenhot removed</info>');
        } else {
            $output->writeln('<error>Heatmap not found</error>');
        }

    }
}
