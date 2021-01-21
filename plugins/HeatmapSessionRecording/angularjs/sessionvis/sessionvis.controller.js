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

    angular.module('piwikApp').controller('SessionRecordingVisController', SessionRecordingVisController);
    // TODO use something like command pattern and redo actions for each action maybe for more effecient and better looking
    // seeking to an earlier position in the video etc: Problem mutations can likely not be "undone"
    SessionRecordingVisController.$inject = ['$scope', 'piwikApi', '$timeout', '$interval', '$filter', '$sce', '$q', 'piwikUrl'];

    function toPrettyTimeFormat(milliseconds)
    {
        var durationSeconds = parseInt(milliseconds / 1000);
        var minutes = parseInt(durationSeconds / 60);
        var secondsLeft = durationSeconds % 60;

        if (minutes < 10) {
            minutes = '0' + String(minutes);
        }

        if (secondsLeft < 10) {
            secondsLeft = '0' + String(secondsLeft);
        }

        return minutes + ':' + secondsLeft;
    }

    function getIframeWindow(iframeElement)
    {
        if (iframeElement && iframeElement.contentWindow) {
            return iframeElement.contentWindow;
        } else if (iframeElement && iframeElement.contentDocument && iframeElement.contentDocument.defaultView) {
            return iframeElement.contentDocument.defaultView;
        }
    }

    var EVENT_TYPE_MOVEMENT = 1;
    var EVENT_TYPE_CLICK = 2;
    var EVENT_TYPE_SCROLL = 3;
    var EVENT_TYPE_RESIZE = 4;
    var EVENT_TYPE_INITIAL_DOM = 5;
    var EVENT_TYPE_MUTATION = 6;
    var EVENT_TYPE_FORM_TEXT = 9;
    var EVENT_TYPE_FORM_VALUE = 10;
    var EVENT_TYPE_SCROLL_ELEMENT = 12;

    function SessionRecordingVisController($scope, piwikApi, $timeout, $interval, $filter, $sce, $q, piwikUrl) {

        var FRAME_STEP = 20;
        var translate = $filter('translate');

        this.isPlaying = false;
        this.progress = 0;
        this.isFinished = false;
        this.isLoading = true;
        this.seekTimeout = null;
        this.lastFramePainted = 0;

        var self = this;
        this.recording = angular.copy(window.sessionRecordingData);

        this.recording.duration = parseInt(this.recording.duration, 10);
        this.durationPretty = toPrettyTimeFormat(this.recording.duration);
        this.positionPretty = '00:00';

        this.previousRecordingId = null;
        this.previousRecordingInfo = null;
        this.nextRecordingId = null;
        this.nextRecordingInfo = null;

        if ($scope.autoPlayEnabled) {
            this.autoplayEnabled = true;
            this.autoplayEnabledText = translate('HeatmapSessionRecording_disable');
        } else {
            this.autoplayEnabled = false;
            this.autoplayEnabledText = translate('HeatmapSessionRecording_enable');
        }

        if ($scope.skipPausesEnabled) {
            this.skipPausesEnabled = true;
            this.skipPausesEnabledText = translate('HeatmapSessionRecording_disable');
        } else {
            this.skipPausesEnabled = false;
            this.skipPausesEnabledText = translate('HeatmapSessionRecording_enable');
        }

        if ($scope.replaySpeed) {
            this.replaySpeed = parseInt($scope.replaySpeed, 10);
        } else {
            this.replaySpeed = 1;
        }

        this.increaseReplaySpeed = function () {
            if (this.replaySpeed === 1) {
                this.replaySpeed = 2;
            } else if (this.replaySpeed === 2) {
                this.replaySpeed = 4;
            } else if (this.replaySpeed === 4) {
                this.replaySpeed = 6;
            } else {
                this.replaySpeed = 1;
            }

            this.updateSettings();
        };

        this.updateSettings = function () {
            var params = {module: 'HeatmapSessionRecording', action: 'saveSessionRecordingSettings'};
            params.autoplay = this.autoplayEnabled ? 1 : 0;
            params.skippauses = this.skipPausesEnabled ? 1 : 0;
            params.replayspeed = this.replaySpeed;

            piwikApi.fetch(params);
        };

        this.embedUrl = '?module=HeatmapSessionRecording&action=embedPage&idSite=' + this.recording.idSite + '&idLogHsr=' + this.recording.idLogHsr + '&idSiteHsr=' + this.recording.idSiteHsr;

        var tokenAuth = piwikUrl.getSearchParam('token_auth')
        if (tokenAuth) {
            this.embedUrl += '&token_auth=' + encodeURIComponent(tokenAuth);
        }

        $sce.trustAsResourceUrl(this.embedUrl);

        var iframeLoadedDeferred = $q.defer();
        var iframeLoadedPromise = iframeLoadedDeferred.promise;

        this.toggleAutoPlay = function () {
            this.autoplayEnabled = !this.autoplayEnabled;
            if (this.autoplayEnabled) {
                this.autoplayEnabledText = translate('HeatmapSessionRecording_disable');
            } else {
                this.autoplayEnabledText = translate('HeatmapSessionRecording_enable');
            }

            this.updateSettings();
        };

        this.toggleSkipPauses = function () {
            this.skipPausesEnabled = !this.skipPausesEnabled;
            if (this.skipPausesEnabled) {
                this.skipPausesEnabledText = translate('HeatmapSessionRecording_disable');
            } else {
                this.skipPausesEnabledText = translate('HeatmapSessionRecording_enable');
            }

            this.updateSettings();
        };

        this.onloaded = function () {
            $timeout(function () {
                // just to be sure we wait for another 500ms
                iframeLoadedDeferred.resolve('loaded');
            }, 500);
        };

        var previousRecordingId = false;
        var hasFoundPrevious = false;
        var hasFoundNext = false;
        var videoPlayerInterval = false;

        angular.forEach(this.recording.pageviews, function (pageview) {
            if (!pageview || !pageview.idloghsr) {
                return;
            }

            if (pageview.idloghsr == self.recording.idLogHsr) {
                hasFoundPrevious = true;
            } else {
                if (!hasFoundPrevious) {
                    self.previousRecordingId = pageview.idloghsr;
                    self.previousRecordingInfo = pageview.label + ' - ' + pageview.server_time_pretty + ' - ' + pageview.time_on_page_pretty;
                } else if (!hasFoundNext) {
                    hasFoundNext = true;
                    self.nextRecordingId = pageview.idloghsr;
                    self.nextRecordingInfo = pageview.label + ' - ' + pageview.server_time_pretty + ' - ' + pageview.time_on_page_pretty;
                }
            }
        });

        this.loadNewRecording = function (idLogHsr) {
            if (idLogHsr) {
                self.isPlaying = false;
                piwik.broadcast.propagateNewPage('idLogHsr=' + parseInt(idLogHsr, 10));
            }
        };

        this.jumpRelative = function (numberSeconds, forward)
        {
            var framesToJump = numberSeconds * 1000;
            var newPosition;
            if (forward) {
                newPosition = self.frame + framesToJump;
                if (newPosition > self.recording.duration) {
                    newPosition = self.recording.duration - FRAME_STEP;
                }
            } else {
                newPosition = self.frame - framesToJump;
                if (newPosition < 0) {
                    newPosition = 0;
                }
            }

            self.seek(newPosition);
        };

        Mousetrap.bind(['space', 'k'], function () {
            self.togglePlay();
        });

        Mousetrap.bind('0', function () {
            if (self.isFinished) {
                self.replay();
            }
        });

        Mousetrap.bind('p', function () {
            self.loadNewRecording(self.previousRecordingId);
        });

        Mousetrap.bind('n', function () {
            self.loadNewRecording(self.nextRecordingId);
        });

        Mousetrap.bind('s', function () {
            self.increaseReplaySpeed();
        });

        Mousetrap.bind('a', function () {
            self.toggleAutoPlay();
        });

        Mousetrap.bind('b', function () {
            self.toggleSkipPauses();
        });

        Mousetrap.bind('left', function () {
            var numSeconds = 5;
            var jumpForward = false;
            self.jumpRelative(numSeconds, jumpForward)
        });

        Mousetrap.bind('right', function() {
            var numSeconds = 5;
            var jumpForward = true;
            self.jumpRelative(numSeconds, jumpForward)
        });

        Mousetrap.bind('j', function () {
            var numSeconds = 10;
            var jumpForward = false;
            self.jumpRelative(numSeconds, jumpForward)
        });

        Mousetrap.bind('l', function () {
            var numSeconds = 10;
            var jumpForward = true;
            self.jumpRelative(numSeconds, jumpForward)
        });

        function initViewport() {
            self.replayHeight = $(window).height() - 48 - $('.sessionRecording .sessionRecordingHead').outerHeight(true) - $('.sessionRecordingPlayer .controls').outerHeight(true);
            self.replayWidth = $(window).width() - 48;

            var minReplayWidth = 400;
            if (self.replayWidth < minReplayWidth && self.recording.viewport_w_px > minReplayWidth) {
                self.replayWidth = minReplayWidth;
            }

            var minReplayHeight = 400;
            if (self.replayHeight < minReplayHeight && self.recording.viewport_h_px > minReplayHeight) {
                self.replayHeight = minReplayHeight;
            }

            var widthScale = 1;
            var heightScale = 1;
            if (self.recording.viewport_w_px > self.replayWidth) {
                widthScale = parseFloat(self.replayWidth / self.recording.viewport_w_px).toFixed(4);
            }

            if (self.recording.viewport_h_px > self.replayHeight) {
                heightScale = parseFloat(self.replayHeight / self.recording.viewport_h_px).toFixed(4);
            }

            self.replayScale = Math.min(widthScale, heightScale);

            self.replayMarginLeft = (self.replayWidth - (self.replayScale * self.recording.viewport_w_px)) / 2;
        }

        this.clues = [];

        var initialMutation = '';

        var timeFrameBuckets = {};
        var bucket;
        var i, left;
        var lastCanvasCoordinates = false, recordingIframe;

        var $timeline = $('.timelineOuter');

        for (i = 0; i < this.recording.events.length; i++) {
            var event = this.recording.events[i];

            if (event && (event.event_type == EVENT_TYPE_INITIAL_DOM || event.event_type == EVENT_TYPE_MUTATION)) {
                if (event.text) {
                    event.text = JSON.parse(event.text);
                }

                if (event.event_type == EVENT_TYPE_INITIAL_DOM || !event.time_since_load || event.time_since_load === '0') {
                    initialMutation = event.text;
                    continue;
                }
            }

            bucket = Math.round(event.time_since_load / FRAME_STEP) * FRAME_STEP;

            if (!(bucket in timeFrameBuckets)) {
                timeFrameBuckets[bucket] = [];
            }

            timeFrameBuckets[bucket].push(event);

            var eventType = '';
            var eventTitle = '';
            if (event.event_type == EVENT_TYPE_CLICK) {
                eventType = 'clickEvent';
                eventTitle = translate('HeatmapSessionRecording_ActivityClick');
            } else if (event.event_type == EVENT_TYPE_MOVEMENT) {
                eventType = 'moveEvent';
                eventTitle = translate('HeatmapSessionRecording_ActivityMove');
            } else if (event.event_type == EVENT_TYPE_SCROLL || event.event_type == EVENT_TYPE_SCROLL_ELEMENT) {
                eventType = 'scrollEvent';
                eventTitle = translate('HeatmapSessionRecording_ActivityScroll');
            } else if (event.event_type == EVENT_TYPE_RESIZE) {
                eventType = 'resizeEvent';
                eventTitle = translate('HeatmapSessionRecording_ActivityResize');
            } else if (event.event_type == EVENT_TYPE_FORM_TEXT || event.event_type == EVENT_TYPE_FORM_VALUE) {
                eventType = 'formChange';
                eventTitle = translate('HeatmapSessionRecording_ActivityFormChange');
            } else if (event.event_type == EVENT_TYPE_INITIAL_DOM || event.event_type == EVENT_TYPE_MUTATION) {
                eventType = 'mutationEvent';
                eventTitle = translate('HeatmapSessionRecording_ActivityPageChange');
            }

            if (eventType) {
                if ((event.time_since_load === 0 || event.time_since_load === '0') && eventType === 'moveEvent') {
                    // this is the initial mouse position and we ignore it in the clues since we cannot draw a line to it
                    continue;
                }

                this.clues.push({
                    left: parseFloat((event.time_since_load / self.recording.duration) * 100).toFixed(2),
                    type: eventType,
                    title: eventTitle
                });
            }
        }

        this.frame = 0;

        this.replay = function () {
            this.isFinished = false;
            this.lastFramePainted = 0;
            this.seek(0);
            this.play();
        };

        this.pause = function () {
            this.isPlaying = false;
        };

        this.togglePlay = function () {
            if (this.isFinished) {
                this.replay();
            } else if (this.isPlaying) {
                this.pause();
            } else {
                this.play();
            }
        };

        this.seekEvent = function (event) {
            var offset = $(event.currentTarget).offset();
            var selectedPosition = event.pageX - offset.left;
            var fullWidth = this.replayWidth;

            var seekPercentage = selectedPosition / fullWidth;
            var seekPositionTime = self.recording.duration * seekPercentage;
            this.seek(seekPositionTime);
        };

        this.seek = function () {
            // implemented further below
        };

        this.play = function () {
            this.isPlaying = true;
        };

        function setViewportResolution(widthPx, heightPx)
        {
            self.recording.viewport_w_px = parseInt(widthPx, 10);
            self.recording.viewport_h_px = parseInt(heightPx, 10);

            $('.recordingWidth').text(widthPx);
            $('.recordingHeight').text(heightPx);
            initViewport();
        }

        $timeout(function () {
            $timeout(function () {
                initViewport();
                $(window).on('resize', initViewport);

                iframeLoadedPromise.then(function () {

                    var iframeElement = document.getElementById('recordingPlayer');
                    recordingIframe = getIframeWindow(iframeElement).recordingFrame;

                    if (!recordingIframe || !recordingIframe.isSupportedBrowser()) {
                        return;
                    }

                    recordingIframe.addClass('html', 'piwikSessionRecording');
                    recordingIframe.addClass('html', 'matomoSessionRecording');

                    self.seek = function (seekToFrame) {
                        // this operation may take a while so we want to stop any interval and further action until this is completed
                        self.isLoading = true;

                        var previousFrame = self.frame;

                        function executeSeek(previousFrame)
                        {
                            for (var crtFrame = previousFrame; crtFrame <= self.frame; crtFrame = crtFrame + FRAME_STEP) {
                                if (crtFrame in timeFrameBuckets) {
                                    for (var j = 0; j < timeFrameBuckets[crtFrame].length; j++) {
                                        self.lastFramePainted = crtFrame;
                                        replayEvent(timeFrameBuckets[crtFrame][j]);
                                    }
                                }
                            }
                        }

                        self.isFinished = false;
                        self.frame = seekToFrame - (seekToFrame % FRAME_STEP);
                        self.progress = parseFloat((self.frame / self.recording.duration) * 100).toFixed(2);
                        self.positionPretty = toPrettyTimeFormat(self.frame);

                        if (previousFrame > self.frame) {
                            // we start replaying the video from the beginning
                            previousFrame = 0;
                            lastCanvasCoordinates = false;

                            recordingIframe.initialMutation(initialMutation);
                            recordingIframe.scrollTo(0,0);

                            setViewportResolution(window.sessionRecordingData.viewport_w_px, window.sessionRecordingData.viewport_h_px);

                            if (self.seekTimeout) {
                                $timeout.cancel(self.seekTimeout);
                                self.seekTimeout = null;
                                // make sure when user goes to previous position and we have a timeout to not execute it multiple times
                            }

                            (function (previousFrame) {
                                self.seekTimeout = $timeout(function () {
                                    executeSeek(previousFrame);
                                    self.isLoading = false;
                                }, 1050);
                            })(previousFrame);

                        } else {
                            // otherwise we instead play fast forward all new actions for faster performance and smoother
                            // visualization etc
                            if (self.seekTimeout) {
                                $timeout.cancel(self.seekTimeout);
                                self.seekTimeout = null;
                            }

                            executeSeek(previousFrame);

                            self.isLoading = false;
                        }
                    };

                    var $mousePointerNode = null;

                    function scrollFrameTo(xPos, yPos)
                    {
                        if (!lastCanvasCoordinates || !$mousePointerNode) {
                            // we cannot move the mouse pointer since we do not have the initial mouse position yet
                            // only perform scroll action instead
                            recordingIframe.scrollTo(xPos, yPos);
                            return;
                        }

                        // we only move the mouse pointer but not draw a line for the mouse movement eg when user scrolls
                        // we also make sure that when the next time the user moves the mouse the mouse move line
                        // will be drawn from this new position

                        var currentScrollTop = recordingIframe.getScrollTop();
                        var currentScrollLeft = recordingIframe.getScrollLeft();

                        recordingIframe.scrollTo(xPos, yPos);

                        // we detect how far down or up user scrolled (or to the left or right)
                        var diffScrollTop = yPos - currentScrollTop;
                        var diffScrollLeft = xPos - currentScrollLeft;

                        // if user scrolled eg 100px down, we also need to move the cursor down
                        var newMousePointerPosLeft = diffScrollLeft + lastCanvasCoordinates.x;
                        var newMousePointerPosTop = diffScrollTop + lastCanvasCoordinates.y;

                        if (newMousePointerPosLeft <= 0) {
                            newMousePointerPosLeft = 0;
                        }
                        if (newMousePointerPosTop <= 0) {
                            newMousePointerPosTop = 0;
                        }

                        // we make sure to draw the next mouse move line  from this position. we use a blue line to indicate
                        // the mouse was moved by a scroll
                        drawMouseLine({x: newMousePointerPosLeft, y: newMousePointerPosTop}, 'blue');
                    }

                    function scrollElementTo(element, xPos, yPos)
                    {
                        if (element && element.scrollTo) {
                            element.scrollTo(xPos, yPos);
                        } else {
                            element.scrollLeft = xPos;
                            element.scrollTop = yPos;
                        }
                    }

                    function drawMouseLine(coordinates, color)
                    {
                        if ($mousePointerNode) {
                            $mousePointerNode.css({left: (coordinates.x - 8) + 'px', top: (coordinates.y - 8) + 'px'});
                        }

                        if (!lastCanvasCoordinates) {
                            return;
                        }

                        recordingIframe.drawLine(lastCanvasCoordinates.x,lastCanvasCoordinates.y, coordinates.x,coordinates.y, color);
                        lastCanvasCoordinates = coordinates;
                    }

                    function moveMouseTo(coordinates)
                    {
                        function resizeStage() {
                            var stageWidth = recordingIframe.getIframeWidth();
                            var stageHeight = recordingIframe.getIframeHeight();
                            recordingIframe.makeSvg(stageWidth, stageHeight);

                            var isPlaying = self.isPlaying;
                            self.isPlaying = false;
                            for (var crtFrame = 0; crtFrame <= self.frame; crtFrame = crtFrame + FRAME_STEP) {
                                if (crtFrame in timeFrameBuckets) {
                                    for (var j = 0; j < timeFrameBuckets[crtFrame].length; j++) {
                                        if (timeFrameBuckets[crtFrame][j] && timeFrameBuckets[crtFrame][j].event_type &&
                                            (timeFrameBuckets[crtFrame][j].event_type == EVENT_TYPE_MOVEMENT
                                            || timeFrameBuckets[crtFrame][j].event_type == EVENT_TYPE_SCROLL
                                            || timeFrameBuckets[crtFrame][j].event_type == EVENT_TYPE_SCROLL_ELEMENT
                                            || timeFrameBuckets[crtFrame][j].event_type == EVENT_TYPE_CLICK)) {

                                            self.lastFramePainted = crtFrame;
                                            replayEvent(timeFrameBuckets[crtFrame][j]);
                                        }
                                    }
                                }
                            }
                            self.isPlaying = isPlaying;
                        }

                        // Runs each time the DOM window resize event fires.
                        // Resets the canvas dimensions to match window,
                        // then draws the new borders accordingly.
                        var iframeWindow = recordingIframe.getIframeWindow();

                        if (!lastCanvasCoordinates) {

                            var stageHeight = recordingIframe.getIframeHeight();
                            var stageWidth = recordingIframe.getIframeWidth();

                            recordingIframe.appendContent('<div class="mousePointer" style="width: 16px;height: 16px;position: absolute;z-index: 99999999;"><svg enable-background="new 0 0 24 24" fill="black" stroke="white" version="1.0" viewBox="0 0 24 24" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M7,2l12,11.2l-5.8,0.5l3.3,7.3l-2.2,1l-3.2-7.4L7,18.5V2"/></svg></div>')
                            $mousePointerNode = recordingIframe.findElement('.mousePointer');
                            recordingIframe.makeSvg(stageWidth, stageHeight);

                            iframeWindow.removeEventListener('resize', resizeStage, false);
                            iframeWindow.addEventListener('resize', resizeStage, false);

                            lastCanvasCoordinates = coordinates;

                            $mousePointerNode.css({left: (coordinates.x - 8) + 'px', top: (coordinates.y - 8) + 'px'});

                            return;
                        }

                        var scrollTop = recordingIframe.getScrollTop();
                        var scrollLeft = recordingIframe.getScrollLeft();

                        if (coordinates.y > (scrollTop + parseInt(self.recording.viewport_h_px, 10))) {
                            recordingIframe.scrollTo(scrollLeft, coordinates.y - 10);
                        } else if (coordinates.y < scrollTop) {
                            recordingIframe.scrollTo(scrollLeft, coordinates.y - 10);
                        }

                        scrollTop = recordingIframe.getScrollTop();

                        if (coordinates.x > (scrollLeft + parseInt(self.recording.viewport_w_px, 10))) {
                            recordingIframe.scrollTo(coordinates.x - 10, scrollTop);
                        } else if (coordinates.x < scrollLeft) {
                            recordingIframe.scrollTo(coordinates.x - 10, scrollTop);
                        }

                        drawMouseLine(coordinates, '#ff9407');
                    }

                    function replayEvent(event)
                    {
                        // fixes some concurrency problems etc by not continueing in the player until the current action is drawn
                        var isPlaying = self.isPlaying;
                        self.isPlaying = false;

                        var offset;

                        if (event.event_type == EVENT_TYPE_MOVEMENT) {
                            if (event.selector) {
                                offset = recordingIframe.getCoordinatesInFrame(event.selector, event.x, event.y, $scope.offsetAccuracy, false);
                                if (offset) {
                                    moveMouseTo(offset);
                                }
                            }
                        } else if (event.event_type == EVENT_TYPE_CLICK) {
                            if (event.selector) {
                                offset = recordingIframe.getCoordinatesInFrame(event.selector, event.x, event.y, $scope.offsetAccuracy, false);

                                if (offset) {
                                    moveMouseTo(offset);
                                    recordingIframe.drawCircle(offset.x, offset.y, '#ff9407');
                                }
                            }
                        } else if (event.event_type == EVENT_TYPE_MUTATION) {
                            if (event.text) {
                                recordingIframe.applyMutation(event.text);
                            }
                        } else if (event.event_type == EVENT_TYPE_SCROLL) {
                            var docHeight = recordingIframe.getIframeHeight();
                            var docWidth = recordingIframe.getIframeWidth();
                            var yPos = parseInt((docHeight / $scope.scrollAccuracy) * event.y, 10);
                            var xPos = parseInt((docWidth / $scope.scrollAccuracy) * event.x, 10);

                            scrollFrameTo(xPos, yPos);
                        } else if (event.event_type == EVENT_TYPE_SCROLL_ELEMENT) {
                            if (event.selector) {
                                var element = recordingIframe.findElement(event.selector);
                                if (element && element.length && element[0]) {
                                    var eleHeight = Math.max(element[0].scrollHeight, element[0].offsetHeight, element.height(), 0);
                                    var eleWidth = Math.max(element[0].scrollWidth, element[0].offsetWidth, element.width(), 0)
                                    if (eleHeight && eleWidth) {
                                        var yPos = parseInt((eleHeight / $scope.scrollAccuracy) * event.y, 10);
                                        var xPos = parseInt((eleWidth / $scope.scrollAccuracy) * event.x, 10);

                                        scrollElementTo(element[0], xPos, yPos);
                                    }
                                }
                            }
                        } else if (event.event_type == EVENT_TYPE_RESIZE) {
                            setViewportResolution(event.x, event.y);
                        } else if (event.event_type == EVENT_TYPE_FORM_TEXT) {
                            if (event.selector) {
                                var formElement = recordingIframe.findElement(event.selector);
                                if (formElement.length) {
                                    var formAttrType = formElement.attr('type');
                                    if (formAttrType && ('' + formAttrType).toLowerCase() === 'file') {
                                        // cannot be changed to local file, would result in error
                                    } else {
                                        formElement.val(event.text).change();
                                    }
                                }
                            }
                        } else if (event.event_type == EVENT_TYPE_FORM_VALUE) {
                            if (event.selector) {
                                var $field = recordingIframe.findElement(event.selector);
                                if ($field.is('input')) {
                                    $field.prop('checked', event.text == 1 ?  true : false);
                                } else if ($field.is('select')) {
                                    $field.val(event.text).change();
                                }
                            }
                        }

                        self.isPlaying = isPlaying;
                    }

                    self.isLoading = false;
                    self.isPlaying = true;

                    var updateTimeCounter = 0;

                    function drawFrames() {
                        if (self.isPlaying && !self.isLoading) {
                            updateTimeCounter++;

                            if (self.frame >= self.recording.duration) {
                                self.isPlaying = false;
                                self.progress = 100;
                                self.isFinished = true;
                                self.positionPretty = self.durationPretty;

                                if (self.autoplayEnabled && self.nextRecordingId) {
                                    self.loadNewRecording(self.nextRecordingId);
                                }
                            } else {
                                self.progress = parseFloat((self.frame / self.recording.duration) * 100).toFixed(2);
                                if (updateTimeCounter === 20) {
                                    updateTimeCounter = 0;
                                    self.positionPretty = toPrettyTimeFormat(self.frame);
                                }
                            }

                            if (self.frame in timeFrameBuckets) {
                                for (var j = 0; j < timeFrameBuckets[self.frame].length; j++) {
                                    // remember when we last painted a frame
                                    self.lastFramePainted = self.frame;
                                    replayEvent(timeFrameBuckets[self.frame][j]);
                                }
                            }

                            if (self.skipPausesEnabled && (self.frame - self.lastFramePainted) > 1800) {
                                // after 1.8 seconds of not painting anything, move forward to next action
                                var keys = Object.keys(timeFrameBuckets);
                                keys = keys.sort(function (a, b) { return a - b; });

                                var hasNextFrame = false;
                                for (var l = 0; l < keys.length; l++) {
                                    if (keys[l] > self.frame) {
                                        hasNextFrame = true;
                                        var isMoreThan1SecInFuture = (keys[l] - self.frame) > 1000;
                                        if (isMoreThan1SecInFuture) {
                                            // we set the pointer foward to the next frame printable
                                            // we only move forward if we can save at least one second
                                            self.frame = keys[l] - (20 * FRAME_STEP); // we set the cursor to shortly before the next action
                                        }
                                        break;
                                    }
                                }

                                // if no frame found, skip to the end of the recording
                                if (!hasNextFrame) {
                                    var isMoreThan1SecInFuture = (self.recording.duration - self.frame) > 1000;
                                    if (isMoreThan1SecInFuture){
                                        self.frame = self.recording.duration - (20 * FRAME_STEP); // we don't set it to very end to still have something to play
                                    }
                                }
                            }

                            self.frame += FRAME_STEP;
                        }
                    }

                    videoPlayerInterval = $interval(function () {
                        var k = 1;
                        for (k; k <= self.replaySpeed; k++) {
                            drawFrames();
                        }
                    }, FRAME_STEP);
                });

            });
        });

        window.onbeforeunload = function () {
            // should improve reload / go to next page performance
            self.isPlaying = false;
            if (videoPlayerInterval) {
                $interval.cancel(videoPlayerInterval);
                videoPlayerInterval = null;
            }
        };

    }
})();