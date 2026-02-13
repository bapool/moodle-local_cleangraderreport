/*
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Clean Grader Report
 *
 * This plugin allows teachers to print student grades more cleanly for parent conferences.
 *
 * @package    local_cleangradereport
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str'], function($, str) {
    'use strict';

    /**
     * Initialize the print grades functionality
     */
    function init() {
        // Add print button to grade report page
        addPrintButton();
    }

    /**
     * Add print button to the grade report
     */
    function addPrintButton() {
        // Wait for page to be ready
        $(document).ready(function() {
            // Look for the grade report content
            var gradeContent = $('.path-grade-report-user, .grade-report-user, [data-region="grade-report"]');

            if (gradeContent.length > 0) {
                // Get current URL parameters
                var urlParams = new URLSearchParams(window.location.search);
                var userid = urlParams.get('userid');
                var courseid = urlParams.get('id');

                if (!userid) {
                    // Try to get from different parameter name or use current user
                    userid = $('input[name="userid"]').val() || M.cfg.userid;
                }

                if (courseid && userid) {
                    // Create print button
                    var printUrl = M.cfg.wwwroot + '/local/cleangradereport/print_report.php?courseid=' +
                                  courseid + '&userid=' + userid;

                    // Get the localized string for the button text
                    str.get_string('printcleanreport', 'local_cleangradereport').then(function(printText) {
                        var printButton = $('<div class="mb-3 local-cleangradereport-print-button">' +
                            '<a href="' + printUrl + '" target="_blank" class="btn btn-primary">' +
                            '<i class="fa fa-print" aria-hidden="true"></i> ' + printText +
                            '</a></div>');

                        // Try different locations to insert the button
                        var insertLocation = $('.grade-report-user table').first();
                        if (insertLocation.length > 0) {
                            insertLocation.before(printButton);
                        } else {
                            // Fallback - insert at top of grade content
                            gradeContent.first().prepend(printButton);
                        }
                    }).catch(function() {
                        // Fallback if string loading fails
                        window.console.error('Failed to load language string');
                    });
                }
            }
        });
    }

    return {
        init: init
    };
});
