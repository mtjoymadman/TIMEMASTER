<?php
/**
 * PDF Generator for TIMEMASTER
 * 
 * This file contains functions to generate PDF reports using TCPDF library
 */

// Define path to TCPDF library
define('TCPDF_PATH', __DIR__ . '/tcpdf/');

/**
 * Generate a PDF from HTML content
 * 
 * @param string $html The HTML content to convert to PDF
 * @param string $title The PDF title
 * @param string $filename The name of the file to download (without .pdf extension)
 * @param boolean $download Whether to force download or display in browser
 * @return void
 */
function generatePdfFromHtml($html, $title = 'TIMEMASTER Report', $filename = 'timemaster_report', $download = true) {
    // Check if TCPDF is available
    if (!file_exists(TCPDF_PATH . 'tcpdf.php')) {
        error_log('TCPDF library not found at: ' . TCPDF_PATH);
        return false;
    }
    
    // Include TCPDF library
    require_once(TCPDF_PATH . 'tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('TIMEMASTER');
    $pdf->SetAuthor('Red Lion Salvage');
    $pdf->SetTitle($title);
    $pdf->SetSubject('Time Clock Report');
    
    // Remove header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Convert dark theme colors to PDF-friendly equivalents
    $html = str_replace('background-color: #222', 'background-color: #F9F9F9', $html);
    $html = str_replace('color: #eee', 'color: #333', $html);
    $html = str_replace('color: #fff', 'color: #000', $html);
    $html = str_replace('color: #bbb', 'color: #666', $html);
    $html = str_replace('background-color: #c0392b', 'background-color: #c0392b; color: #FFFFFF', $html);
    $html = str_replace('background-color: #2c3e50', 'background-color: #E8F4F8', $html);
    $html = str_replace('border-bottom: 1px solid #444', 'border-bottom: 1px solid #DDD', $html);
    
    // Write HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    if ($download) {
        $pdf->Output($filename . '.pdf', 'D'); // 'D' means force download
    } else {
        $pdf->Output($filename . '.pdf', 'I'); // 'I' means show in browser
    }
    
    return true;
}

/**
 * Generate a time report PDF
 * 
 * @param array $report_data The report data
 * @param string $start_date Start date of the report period (Y-m-d)
 * @param string $end_date End date of the report period (Y-m-d)
 * @param array $fields Fields to include in the report
 * @param string $filename The name of the file to download (without .pdf extension)
 * @param boolean $download Whether to force download or display in browser
 * @return void
 */
function generateTimeReportPdf($report_data, $start_date, $end_date, $fields, $filename = 'time_report', $download = true) {
    // Generate HTML for the report
    $html = '<div style="font-family: helvetica, sans-serif;">';
    $html .= '<h1 style="color: #c0392b; margin-bottom: 10px;">Time Clock Report</h1>';
    $html .= '<p style="font-size: 14px; color: #666; margin-bottom: 20px;">Period: ' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)) . '</p>';
    
    if (empty($report_data)) {
        $html .= '<p style="color: #666; font-style: italic; padding: 20px; text-align: center;">No data found for the selected period.</p>';
    } else {
        foreach ($report_data as $username => $records) {
            $html .= '<div style="margin-bottom: 30px;">';
            $html .= '<h2 style="color: #333; padding-bottom: 5px; border-bottom: 2px solid #c0392b; margin-bottom: 15px;">' . htmlspecialchars($username) . '</h2>';
            $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
            $html .= '<thead><tr style="background-color: #c0392b; color: white;">';
            $html .= '<th style="padding: 8px; text-align: left;">Date</th>';
            if (in_array('clock_in', $fields)) $html .= '<th style="padding: 8px; text-align: left;">First Clock In</th>';
            if (in_array('clock_out', $fields)) $html .= '<th style="padding: 8px; text-align: left;">Last Clock Out</th>';
            if (in_array('total_hours', $fields)) $html .= '<th style="padding: 8px; text-align: left;">Total Hours</th>';
            if (in_array('breaks', $fields)) $html .= '<th style="padding: 8px; text-align: left;">Breaks Taken</th>';
            if (in_array('break_time', $fields)) $html .= '<th style="padding: 8px; text-align: left;">Break Time (min)</th>';
            if (in_array('reason', $fields)) $html .= '<th style="padding: 8px; text-align: left;">Reason</th>';
            if (in_array('record_type', $fields)) $html .= '<th style="padding: 8px; text-align: left;">Type</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($records as $record) {
                // Determine row style based on record type
                $row_style = $record['record_type'] == 'outside' ? 'background-color: #E8F4F8;' : 'background-color: #FFFFFF;';
                
                $html .= '<tr style="' . $row_style . '">';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD;">' . date('M d, Y', strtotime($record['work_date'])) . '</td>';
                if (in_array('clock_in', $fields)) {
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD;">' . date('h:i A', strtotime($record['first_clock_in'])) . '</td>';
                }
                if (in_array('clock_out', $fields)) {
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD;">' . ($record['last_clock_out'] ? date('h:i A', strtotime($record['last_clock_out'])) : '-') . '</td>';
                }
                if (in_array('total_hours', $fields)) {
                    $hours = floor($record['total_minutes'] / 60);
                    $minutes = $record['total_minutes'] % 60;
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD; font-weight: bold; color: #c0392b;">' . $hours . 'h ' . $minutes . 'm</td>';
                }
                if (in_array('breaks', $fields)) {
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD;">' . $record['breaks_taken'] . '</td>';
                }
                if (in_array('break_time', $fields)) {
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD;">' . $record['total_break_time'] . '</td>';
                }
                if (in_array('reason', $fields)) {
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD; max-width: 200px; word-wrap: break-word;">' . (!empty($record['reason']) ? htmlspecialchars($record['reason']) : '-') . '</td>';
                }
                if (in_array('record_type', $fields)) {
                    $type_style = $record['record_type'] == 'outside' ? 'background-color: #e74c3c; color: white;' : 'background-color: #27ae60; color: white;';
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #DDD;"><span style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; ' . $type_style . '">' . 
                        ($record['record_type'] == 'outside' ? 'Outside Time' : 'Regular') . '</span></td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }
    }
    $html .= '</div>';
    
    // Generate the PDF
    return generatePdfFromHtml($html, 'TIMEMASTER Report', $filename, $download);
} 