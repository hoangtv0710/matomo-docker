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
namespace Piwik\Plugins\AbTesting;
use Piwik\Common;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor;
use Piwik\Segment\SegmentExpression;

/**
 * AbTesting segment base class
 */
class Segment extends \Piwik\Plugin\Segment
{
    const NAME_EXPERIMENT_SEGMENT = 'abtesting_experiment';
    const NAME_VARIATION_SEGMENT = 'abtesting_variation';
    const NAME_ENTERED_SEGMENT = 'abtesting_entered';

    protected function init()
    {
        $this->setCategory('AbTesting_Experiments');
    }

    public static function getIdByName($valueToMatch, $sqlField, $matchType, $segmentName)
    {
        if ($segmentName === self::NAME_EXPERIMENT_SEGMENT) {
            $sql = 'SELECT idexperiment FROM ' . Common::prefixTable('experiments') . ' WHERE ';

        } elseif ($segmentName === self::NAME_VARIATION_SEGMENT) {
            if ($valueToMatch === RequestProcessor::VARIATION_NAME_ORIGINAL || empty($valueToMatch)) {
                return RequestProcessor::VARIATION_ORIGINAL_ID;
            }

            $sql = 'SELECT idvariation FROM ' . Common::prefixTable('experiments_variations') . ' WHERE ';
        } else {
            throw new \Exception("Invalid use of segment filter method");
        }

        switch ($matchType) {
            case SegmentExpression::MATCH_NOT_EQUAL:
                $where = ' name != ? ';
                break;
            case SegmentExpression::MATCH_EQUAL:
                $where = ' name = ? ';
                break;
            case SegmentExpression::MATCH_CONTAINS:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' name LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_DOES_NOT_CONTAIN:
                $where = ' name NOT LIKE CONCAT(\'%\', ?, \'%\') ';
                break;
            case SegmentExpression::MATCH_STARTS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' name LIKE CONCAT(?, \'%\') ';
                break;
            case SegmentExpression::MATCH_ENDS_WITH:
                // use concat to make sure, no %s occurs because some plugins use %s in their sql
                $where = ' name LIKE CONCAT(\'%\', ?) ';
                break;
            default:
                throw new \Exception("This match type $matchType is not available for A/B test segments.");
                break;
        }

        $sql .= $where;

        return array('SQL' => $sql, 'bind' => $valueToMatch);
    }
}

