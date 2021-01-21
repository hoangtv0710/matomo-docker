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

(function () {
    angular.module('piwikApp').controller('HeatmapVisController', HeatmapVisController);

    HeatmapVisController.$inject = ['$scope', 'piwikApi', 'piwik', 'piwikUrl', '$timeout', '$q', '$filter', '$sce', 'piwikUrl'];

    var heightPerHeatmap = 32000;

    var userAgent = String(navigator.userAgent).toLowerCase();
    if (userAgent.match(/(iPod|iPhone|iPad|Android|IEMobile|Windows Phone)/i)) {
        heightPerHeatmap = 2000;
    } else if (userAgent.indexOf('msie ') > 0 || userAgent.indexOf('trident/') > 0 || userAgent.indexOf('edge') > 0) {
        heightPerHeatmap = 8000;
    }

    function getIframeWindow(iframeElement)
    {
        if (iframeElement && iframeElement.contentWindow) {
            return iframeElement.contentWindow;
        } else if (iframeElement && iframeElement.contentDocument && iframeElement.contentDocument.defaultView) {
            return iframeElement.contentDocument.defaultView;
        }
    }

    function removeScrollHeatmap() {
        $('.iframeRecordingContainer .scrollHeatmapLeaf').remove();
    };

    function scrollHeatmap(recordingIframe, scrollReaches)
    {
        var $iframe = $('#recordingPlayer');
        // we first set the iframe to the initial 400px again so we can for sure detect the current height of the
        // inner iframe body correctly
        $iframe.css('height', '400px');
        var documentHeight = recordingIframe.getIframeHeight();
        $iframe.css('height', documentHeight + 'px');

        var numIntervals = 1000;
        var heightToIntervalRatio = documentHeight / numIntervals;

        function map(value, istart, istop, ostart, ostop) {
            return ostart + (ostop - ostart) * ((value - istart) / (istop - istart));
        }

        var numViewersTotal = 0;
        for (var i = 0; i < scrollReaches.length; i++) {
            numViewersTotal = numViewersTotal + parseInt(scrollReaches[i].value, 10);
        }

        var buckets = [];
        var num_viewers = 0, lastBucket = null, percentage = 100, scrollReach, scrollDepth, reachScrolledFromPosition = 0;
        // reachScrolledFromPosition we start from 0, and then always paint to the next bucket. eg when scrollReach is 27
        // and scrollDepth is 35, then we know that 27 people have scrolled down to 3.5% of the page.
        for (var i = 0; i < scrollReaches.length; i++) {

            // the number of people that reached this point
            scrollReach = parseInt(scrollReaches[i].value, 10);

            // how far down they scrolled
            scrollDepth = parseInt(scrollReaches[i].label, 10);

            var reachScrolledToPosition = Math.round(scrollDepth * heightToIntervalRatio);

            if (lastBucket && lastBucket.position === reachScrolledToPosition) {
                // when page is < 1000 we need to aggregate buckets
                num_viewers = num_viewers + scrollReach;
            } else {
                if (numViewersTotal !== 0) {
                    percentage = ((numViewersTotal - num_viewers) / numViewersTotal) * 100;
                }
                num_viewers = num_viewers + scrollReach;
                // percentage.toFixed(1) * 10 => convert eg 99.8 => 998
                lastBucket = {percentageValue: percentage.toFixed(1) * 10, position: reachScrolledFromPosition, percent: percentage.toFixed(1)};
                buckets.push(lastBucket);
            }

            reachScrolledFromPosition = reachScrolledToPosition;
        }

        function mapColorIntensity(intensity, min, max) {
            if (min === max || (!min && !max)){
                return [255, 255, 0];
            }

            var cint = map(intensity, min, max, 0, 255);
            var step = (max - min) / 5;

            if (cint > 204) {
                return [255, map(intensity, max - step, max, 255,0), 0];
            }
            if (cint > 153) {
                return [map(intensity, max - 2 * step, max - step, 0, 255), 255, 0];
            }
            if (cint > 102) {
                return [0, 255, map(intensity, max - 3 * step, max - 2 * step, 255, 0)];
            }
            if (cint > 51) {
                return [0, map(intensity, max - 4 * step,max - 3 * step, 0, 255), 255];
            }

            return [map(intensity, min,max - 4 * step, 255 ,0), 0, 255];
        }

        if (buckets.length) {
            // we need to make sure to draw scroll heatmap over full page
            var found = false;
            angular.forEach(buckets, function (bucket) {
                if (bucket.position === 0 || bucket.position === '0') {
                    found = true;
                }
            });
            if (!found) {
                buckets.unshift({percent: '100.0', percentageValue: 1000, position: 0})
            }
        } else {
            // we'll show full page as not scrolled
            buckets.push({percent: '0', percentageValue: 0, position: 0});
        }

        var minValue = 0;
        var maxValue = 1000; // max value is always 1000 (=100%)

        if (buckets && buckets.length && buckets[0]) {
            minValue = buckets[buckets.length - 1].percentageValue;
        }

        var iframeWidth = $iframe.width();
        var bucket, nextBucket, height, top, percent;
        for (var index = 0; index < buckets.length; index++) {
            bucket = buckets[index];
            if (buckets[index + 1]){
                nextBucket = buckets[index + 1];
            } else {
                nextBucket = {position: documentHeight};
            }

            top = bucket.position;
            height = nextBucket.position - bucket.position;

            if (height === 0) {
                height = 1; // make sure to draw at least one px
            }
            percent = bucket.percent + ' percent reached this point';

            var color = mapColorIntensity(bucket.percentageValue, minValue, maxValue);
            for (var j = 0; j < color.length; j++) {
                color[j] = parseInt(color[j]);
            }
            color = 'rgb(' + color.join(',') + ')';

            $('.iframeRecordingContainer').append('<div class="scrollHeatmapLeaf" title="' + percent +'" style="width: ' + iframeWidth +'px;height: ' + height + 'px;left: 0;top: ' + top +'px; background-color: ' + color + ';"></div>');
        }

        $('.iframeRecordingContainer .scrollHeatmapLeaf').tooltip({
            track: true,
            items: '*',
            tooltipClass: 'heatmapTooltip',
            show: false,
            hide: false
        });

        $('.legend-area .min').text(parseFloat(minValue / 10).toFixed(1) + '%');
        $('.legend-area .max').text(parseFloat(maxValue / 10).toFixed(1) + '%');
    };

    function initHeatmap(recordingIframe) {

        var $iframe = $('#recordingPlayer');
        // we first set the iframe to the initial 400px again so we can for sure detect the current height of the
        // inner iframe body correctly
        $iframe.css('height', '400px');

        var documentHeight = recordingIframe.getIframeHeight();
        $iframe.css('height', documentHeight + 'px');

        var heatmapContainer = $('#heatmapContainer');
        heatmapContainer.css('height', documentHeight + 'px');
        heatmapContainer.css('width', $iframe.width() + 'px');
        heatmapContainer.empty();

        var numHeatmaps = Math.ceil(documentHeight / heightPerHeatmap);
        for (var i = 1; i <= numHeatmaps; i++) {
            var height = heightPerHeatmap;
            if (i === numHeatmaps) {
                height = documentHeight % heightPerHeatmap;
            }
            heatmapContainer.append('<div id="heatmap' + i +'" class="heatmapTile"></div>');
            heatmapContainer.find('#heatmap' + i).css({height: height + 'px'});
        }

        return numHeatmaps;
    }

    function renderHeatmap(recordingIframe, dataPoints) {

        var numHeatmaps = initHeatmap(recordingIframe);

        var legendCanvas = document.createElement('canvas');
        legendCanvas.width = 100;
        legendCanvas.height = 10;
        var min = document.querySelector('.legend-area .min');
        var max = document.querySelector('.legend-area .max');
        var gradientImg = document.querySelector('.legend-area .gradient');
        var legendCtx = legendCanvas.getContext('2d');
        var gradientCfg = {};
        function updateLegend(data) {
            // the onExtremaChange callback gives us min, max, and the gradientConfig
            // so we can update the legend
            min.innerHTML = data.min;
            max.innerHTML = data.max;
            // regenerate gradient image
            if (data.gradient != gradientCfg) {
                gradientCfg = data.gradient;
                var gradient = legendCtx.createLinearGradient(0, 0, 100, 1);
                for (var key in gradientCfg) {
                    gradient.addColorStop(key, gradientCfg[key]);
                }
                legendCtx.fillStyle = gradient;
                legendCtx.fillRect(0, 0, 100, 10);
                gradientImg.src = legendCanvas.toDataURL();
            }
        };

        var heatmapInstances = [];
        for (var i = 1; i <= numHeatmaps; i++) {
            var dpoints = {min: dataPoints.min, max: dataPoints.max, data: []};
            var config = {
                container: document.getElementById('heatmap' + i),
                radius: 10,
                maxOpacity: .5,
                minOpacity: 0,
                blur: .75
            };
            if (i === 1) {
                config.onExtremaChange = function(data) {
                    updateLegend(data);
                };
            }

            if (dataPoints && dataPoints.data && dataPoints.data.length >= 20000) {
                config.radius = 8;
            } else if (dataPoints && dataPoints.data && dataPoints.data.length >= 2000) {
                config.radius = 9;
            }

            if (numHeatmaps === 1) {
                dpoints.data = dataPoints.data;
            } else {
                var lowerLimit = (i - 1) * heightPerHeatmap;
                var upperLimit = lowerLimit + heightPerHeatmap - 1;

                for (var j = 0; j < dataPoints.data.length; j++) {
                    if (dataPoints.data[j].y >= lowerLimit && dataPoints.data[j].y <= upperLimit) {
                        var thePoint = angular.copy(dataPoints.data[j]);
                        thePoint.y = thePoint.y - lowerLimit;
                        dpoints.data.push(thePoint);
                    }
                }
            }

            var heatmapInstance = h337.create(config);
            heatmapInstance.setData(dpoints);
            heatmapInstances.push(heatmapInstance);
        }

        return heatmapInstances;
    };

    function HeatmapVisController($scope, piwikApi, piwik, piwikUrl, $timeout, $q, $filter, $sce, piwikUrl) {
        var self = this;
        var deviceDesktop = 1;
        var deviceTablet = 2;
        var deviceMobile = 3;

        var desktopPreviewSize = 1280;
        var translate = $filter('translate');

        this.isLoading = false;
        this.heatmapTypes = $scope.heatmapTypes;
        this.deviceTypes = $scope.deviceTypes;
        this.iframeWidth = desktopPreviewSize;
        this.customIframeWidth = this.iframeWidth;
        this.avgFold = 0;
        this.iframeWidthOptions = [];
        this.isHeatmapActive = $scope.isActive === '1' || $scope.isActive === 1;
        this.hasWriteAccess = piwik && piwik.heatmapWriteAccess;
        this.showDeleteScreenshot = this.isHeatmapActive && this.hasWriteAccess;

        this.embedUrl = '?module=HeatmapSessionRecording&action=embedPage&idSite=' + piwik.idSite + '&idSiteHsr=' + $scope.idSiteHsr;
        var tokenAuth = piwikUrl.getSearchParam('token_auth')
        if (tokenAuth) {
            this.embedUrl += '&token_auth=' + encodeURIComponent(tokenAuth);
        }

        $sce.trustAsResourceUrl(this.embedUrl);

        $scope.breakpointMobile = parseInt($scope.breakpointMobile, 10);
        $scope.breakpointTablet = parseInt($scope.breakpointTablet, 10);

        var iframeResolutions = [320, 360, 480, 600, 640, 900, 960, 1024, 1200, 1280, 1366, 1440, 1600, 1680, 1920, 2560];

        if (-1 === iframeResolutions.indexOf($scope.breakpointMobile)) {
            iframeResolutions.push($scope.breakpointMobile);
        }

        if (-1 === iframeResolutions.indexOf($scope.breakpointTablet)) {
            iframeResolutions.push($scope.breakpointTablet);
        }

        iframeResolutions = iframeResolutions.sort(function(a, b) {
            return a - b;
        })

        angular.forEach(iframeResolutions, function (width) {
            self.iframeWidthOptions.push({key: width, value: String(width) + 'px'});
        });

        this.heatmapType = this.heatmapTypes[0].key;
        this.deviceType = this.deviceTypes[0].key;

        function isScrollHeatmapType()
        {
            return self.heatmapType === 3 || self.heatmapType === '3';
        }

        function assignNumSamplesToDevices()
        {
            angular.forEach(self.deviceTypes, function (deviceType, index) {
                if ($scope.numSamples['nb_samples_device_' + deviceType.key]){
                    deviceType.numSamples = $scope.numSamples['nb_samples_device_' + deviceType.key];
                } else {
                    deviceType.numSamples = 0;
                }

                deviceType.tooltip = translate('HeatmapSessionRecording_XSamples', deviceType.name + ' - ' + deviceType.numSamples);

                self.deviceTypes[index] = deviceType;
            });

            $('.deviceAllCountSamples').text($scope.numSamples['nb_samples_device_all']);
        }
        assignNumSamplesToDevices();

        var heatmapInstances = null;
        var recordingIframe = null;

        var heatmapDataPromise = null;
        var heatmapMetaDataPromise = null;
        var iframeLoadedDeferred = $q.defer();
        var iframeLoadedPromise = iframeLoadedDeferred.promise;

        this.deleteScreenshot = function () {
            piwik.helper.modalConfirm('#confirmDeleteHeatmapScreenshot', {yes: function () {
                var requestParams = {
                    method: 'HeatmapSessionRecording.deleteHeatmapScreenshot',
                    idSiteHsr: $scope.idSiteHsr
                };

                self.isLoading = true;
                heatmapDataPromise = piwikApi.fetch(requestParams).then(function () {
                    self.isLoading = false;
                    location.reload();
                });
            }});
        };

        this.onloaded = function () {
            iframeLoadedDeferred.resolve('loaded');
        };

        this.fetchHeatmap = function () {
            removeScrollHeatmap();

            if (heatmapInstances) {
                angular.forEach(heatmapInstances, function (heatmapInstance) {
                    heatmapInstance.setData({max: 1, min: 0, data: []});
                });
            }

            if (heatmapDataPromise) {
                heatmapDataPromise.abort();
                heatmapDataPromise = null;
            }

            if (heatmapMetaDataPromise) {
                heatmapMetaDataPromise.abort();
                heatmapMetaDataPromise = null;
            }

            this.isLoading = true;
            this.avgFold = 0;

            var requestParams = {
                method: 'HeatmapSessionRecording.getRecordedHeatmap',
                idSiteHsr: $scope.idSiteHsr,
                heatmapType: this.heatmapType,
                deviceType: this.deviceType,
                period: $scope.heatmapPeriod,
                date: $scope.heatmapDate,
                filter_limit: -1
            };
            if (piwikUrl.getSearchParam('segment')) {
                requestParams.segment = piwikUrl.getSearchParam('segment');
            }

            heatmapDataPromise = piwikApi.fetch(requestParams);

            var requestParams2 = angular.copy(requestParams);
            requestParams2.method = 'HeatmapSessionRecording.getRecordedHeatmapMetadata';
            heatmapMetaDataPromise = piwikApi.fetch(requestParams2);

            $q.all([heatmapDataPromise, heatmapMetaDataPromise, iframeLoadedPromise]).then(function (response) {

                if (!recordingIframe) {
                    var iframeElement = document.getElementById('recordingPlayer');
                    recordingIframe = getIframeWindow(iframeElement).recordingFrame;
                    recordingIframe.excludeElements($scope.excludedElements);
                    recordingIframe.addClass('html', 'piwikHeatmap');
                    recordingIframe.addClass('html', 'matomoHeatmap');
                    recordingIframe.addWorkaroundForSharepointHeatmaps();
                }

                initHeatmap(recordingIframe);
                removeScrollHeatmap();

                var rows = response[0];

                $scope.numSamples = response[1];
                if (angular.isArray($scope.numSamples) && $scope.numSamples[0]) {
                    $scope.numSamples = $scope.numSamples[0];
                }
                assignNumSamplesToDevices();

                self.isLoading = false;

                if (isScrollHeatmapType()) {
                    scrollHeatmap(recordingIframe, rows);
                } else {

                    var dataPoints = {min: 0, max: 0, data: []};

                    for (var i = 0; i < rows.length; i++) {
                        var row = rows[i];
                        if (row.selector) {

                            var dataPoint = recordingIframe.getCoordinatesInFrame(row.selector, row.offset_x, row.offset_y, $scope.offsetAccuracy, true);

                            if (dataPoint) {
                                dataPoint.value = row.value;
                                dataPoints.data.push(dataPoint);
                            }
                        }
                    }

                    if (self.heatmapType === 2 || self.heatmapType === '2') {
                        // click
                        var numEntriesHigherThan1 = 0;
                        for (var i = 0; i < dataPoints.data.length; i++) {
                            if (dataPoints.data[i] && dataPoints.data[i].value && dataPoints.data[i].value > 1) {
                                numEntriesHigherThan1++;
                            }
                        }

                        if ((numEntriesHigherThan1 / dataPoints.data.length) >= 0.10 && dataPoints.data.length > 120) {
                            // if at least 10% have .value >= 2, then we set max to 2 to differntiate better between 1 and 2 clicks
                            // but only if we also have more than 80 data points ("randomly" chosen that threshold)
                            dataPoints.max = 2;
                        } else {
                            dataPoints.max = 1;
                        }

                    } else {
                        var LIMIT_MAX_DATA_POINT = 10;

                        var values = {},value;
                        for (var i = 0; i < dataPoints.data.length; i++) {
                            if (!dataPoints.data[i] || !dataPoints.data[i].value) {
                                continue;
                            }
                            value = parseInt(dataPoints.data[i].value, 10);

                            if (dataPoints.data[i].value > dataPoints.max) {
                                dataPoints.max = value;
                            }
                            if (value > LIMIT_MAX_DATA_POINT) {
                                value = LIMIT_MAX_DATA_POINT;
                            }
                            if (value in values) {
                                values[value]++;
                            } else {
                                values[value] = 0;
                            }
                        }

                        if (dataPoints.max > LIMIT_MAX_DATA_POINT) {
                            // we limit it to 10 otherwise many single points are not visible etc
                            // if there is no single entry having value 10, we set it to 9, 8 or 7
                            // to make sure there is actually a dataPoint for this max value.
                            var k;
                            var sumValuesAboveThreshold = 0;
                            for (k = LIMIT_MAX_DATA_POINT; k > 1; k--) {
                                if (k in values) {
                                    // we need to aggregate the value
                                    sumValuesAboveThreshold += values[k];
                                }

                                if ((sumValuesAboveThreshold / dataPoints.data.length) >= 0.2) {
                                    // we make sure to have at least 20% of entries in that max value
                                    dataPoints.max = k;
                                    break;
                                }
                                // todo ideally in this case also require that akk 2 - (k-1) have a distribution of 0.2
                                // to make sure we have enough values in between, and if not select k-1 or so. Otherwise
                                // we have maybe 75% with value 1, 20% with value 10, and only 5% in between... which would
                                // be barely visible those 75% maybe
                            }
                            if (dataPoints.max > LIMIT_MAX_DATA_POINT) {
                                // when no entry has more than 15% distribution, we set a default of 5
                                dataPoints.max = 5;
                                for (k = 5; k > 0; k--) {
                                    if (k in values) {
                                        // we limit it to 10 otherwise many single points are not visible etc
                                        // also if there is no single entry having value 10, we set it to 9, 8 or 7
                                        // to make sure there is actually a dataPoint for this max value.
                                        dataPoints.max = k;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    heatmapInstances = renderHeatmap(recordingIframe, dataPoints);

                    if ($scope.numSamples && $scope.numSamples['avg_fold_device_' + self.deviceType]) {
                        var avgFoldPercent = parseFloat($scope.numSamples['avg_fold_device_' + self.deviceType]);
                        var height = recordingIframe.getIframeHeight();
                        if (height) {
                            self.avgFold = parseInt((avgFoldPercent / 100) * height, 10);
                        }
                    }
                }
            }, function () {
                self.isLoading = false;
            });
        };

        this.changeDeviceType = function (deviceType) {
            this.deviceType = deviceType;

            if (deviceType == deviceDesktop) {
                this.changeIframeWidth(desktopPreviewSize, false);
            } else if (deviceType == deviceTablet) {
                this.changeIframeWidth($scope.breakpointTablet || 960, false);
            } else if (deviceType == deviceMobile) {
                this.changeIframeWidth($scope.breakpointMobile || 600, false);
            }
        };

        this.changeIframeWidth = function (iframeWidth, scrollToTop) {
            this.iframeWidth = iframeWidth;
            this.customIframeWidth = iframeWidth;

            this.fetchHeatmap();

            if (scrollToTop) {
                piwik.helper.lazyScrollToContent();
            }
        };

        this.changeHeatmapType = function (heatmapType) {
            this.heatmapType = heatmapType;
            this.fetchHeatmap();
        };

        $scope.$on('$destroy', function() {
            removeScrollHeatmap();
        });

        this.fetchHeatmap();
    }
})();