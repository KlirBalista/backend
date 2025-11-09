<?php

namespace App\Services;

class PDFService
{
    public static function generateStatementOfAccount($soaData, $patient, $admission, $facility = null)
    {
        // Create new PDF document - we'll use TCPDF which is compatible with Laravel
        $pdf = new \TCPDF();
        $pdf->SetCreator('BCSystem');
        $pdf->SetAuthor($facility->name ?? 'Health Care Facility');
        $pdf->SetTitle('Statement of Account');
        
        // Set margins and auto page break
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        // Add a page
        $pdf->AddPage();
        
        // Add header
        self::addHeader($pdf, $facility);
        
        // Add patient information
        self::addPatientInfo($pdf, $patient, $admission, $soaData);
        
        // Add two-column layout with charges and payments
        self::addTwoColumnLayout($pdf, $soaData);
        
        // Add signature section
        self::addSignatureSection($pdf);
        
        return $pdf;
    }
    
    private static function addHeader($pdf, $facility = null)
    {
        // Debug: Log what facility data we received
        if ($facility) {
            \Log::info('PDFService Header - Received Facility Data:', [
                'name' => $facility->name ?? 'NULL',
                'address' => $facility->address ?? 'NULL',
                'contact_number' => $facility->contact_number ?? 'NULL',
                'description' => $facility->description ?? 'NULL',
                'facility_object_vars' => get_object_vars($facility)
            ]);
        } else {
            \Log::warning('PDFService Header - No facility data received');
        }
        
        // Use facility name - the controller already uppercases it
        $facilityName = 'HEALTH CARE FACILITY'; // Default fallback
        if ($facility && isset($facility->name) && !empty(trim($facility->name)) && $facility->name !== 'N/A') {
            $facilityName = $facility->name; // Already uppercased by controller
            \Log::info('PDFService Header - Using facility name: ' . $facilityName);
        } else {
            \Log::info('PDFService Header - Using default name: ' . $facilityName);
        }
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, $facilityName, 0, 1, 'C');
        
        // Use facility description or address as subtitle
        $subtitle = 'Health Care Services'; // Default fallback
        if ($facility) {
            // Try description first
            if (isset($facility->description) && !empty(trim($facility->description)) && $facility->description !== 'N/A') {
                $subtitle = trim($facility->description);
            }
            // If no description, try address
            elseif (isset($facility->address) && !empty(trim($facility->address)) && $facility->address !== 'N/A') {
                $subtitle = trim($facility->address);
            }
        }
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $subtitle, 0, 1, 'C');
        
        // Add contact number if available and not N/A
        if ($facility && isset($facility->contact_number) && !empty(trim($facility->contact_number)) && $facility->contact_number !== 'N/A') {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'Contact: ' . trim($facility->contact_number), 0, 1, 'C');
        }
        
        // Statement of Account title
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, 'STATEMENT OF ACCOUNT', 'B', 1, 'C');
        $pdf->Ln(8);
    }
    
    private static function addPatientInfo($pdf, $patient, $admission, $soaData)
    {
        // SOA Header Info
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetY($pdf->GetY() + 5);
        
        // SOA Reference and Status on same line
        $soaNumber = $soaData->soa_number ?? 'SOA-' . date('Ymd-His');
        $soaStatus = 'Balance Remaining'; // You can calculate this based on data
        $dueDate = date('d/m/Y');
        
        $pdf->Cell(50, 5, 'SOA Number:', 0, 0, 'L');
        $pdf->Cell(45, 5, $soaNumber, 'B', 0, 'L');
        $pdf->Cell(25, 5, 'Status:', 0, 0, 'L');
        $pdf->Cell(45, 5, $soaStatus, 'B', 0, 'L');
        $pdf->Ln(8);
        
        $pdf->Cell(50, 5, 'Date Generated:', 0, 0, 'L');
        $pdf->Cell(45, 5, date('d/m/Y h:i A'), 'B', 0, 'L');
        $pdf->Cell(25, 5, 'Due Date:', 0, 0, 'L');
        $pdf->Cell(45, 5, $dueDate, 'B', 0, 'L');
        $pdf->Ln(8);
        
        $pdf->Cell(50, 5, 'Bill Number:', 0, 0, 'L');
        $billNumber = $soaData->current_bill->bill_number ?? 'BILL-' . date('Ymd');
        $pdf->Cell(45, 5, $billNumber, 'B', 0, 'L');
        $pdf->Cell(25, 5, 'Billing Period:', 0, 0, 'L');
        $billingPeriod = $admission ? 'Admission ' . date('F j, Y', strtotime($admission->admission_date)) : 'Current';
        $pdf->Cell(45, 5, $billingPeriod, 'B', 0, 'L');
        $pdf->Ln(15);
        
        // Patient Information Section
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'PATIENT INFORMATION', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'Full Name:', 0, 0, 'L');
        $patientName = trim($patient->first_name . ' ' . ($patient->middle_name ? $patient->middle_name . ' ' : '') . $patient->last_name);
        $pdf->Cell(45, 6, $patientName, 'B', 0, 'L');
        $pdf->Cell(25, 6, 'Age:', 0, 0, 'L');
        $age = self::calculateAge($patient->date_of_birth) . ' years old';
        $pdf->Cell(45, 6, $age, 'B', 0, 'L');
        $pdf->Ln(8);
        
        $pdf->Cell(50, 6, 'Date of Birth:', 0, 0, 'L');
        $dob = $patient->date_of_birth ? date('d/m/Y', strtotime($patient->date_of_birth)) : 'N/A';
        $pdf->Cell(45, 6, $dob, 'B', 0, 'L');
        $pdf->Cell(25, 6, 'Address:', 0, 0, 'L');
        $pdf->Cell(45, 6, $patient->address ?? '', 'B', 0, 'L');
        $pdf->Ln(8);
        
        $pdf->Cell(50, 6, 'Contact Number:', 0, 0, 'L');
        $pdf->Cell(45, 6, $patient->contact_number ?? '', 'B', 0, 'L');
        $pdf->Cell(25, 6, 'Philhealth Category:', 0, 0, 'L');
        $pdf->Cell(45, 6, $patient->philhealth_category ?? 'Direct', 'B', 0, 'L');
        $pdf->Ln(8);
        
        $pdf->Cell(50, 6, 'Philhealth No.:', 0, 0, 'L');
        $pdf->Cell(115, 6, $patient->philhealth_number ?? '', 'B', 0, 'L');
        $pdf->Ln(15);
        
        // Admission Information Section
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'ADMISSION INFORMATION', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'Admission Date:', 0, 0, 'L');
        $admissionDate = $admission && $admission->admission_date ? 
            date('d/m/Y', strtotime($admission->admission_date)) : 'N/A';
        $pdf->Cell(45, 6, $admissionDate, 'B', 0, 'L');
        $pdf->Cell(25, 6, 'Admission Type:', 0, 0, 'L');
        $admissionType = $admission->admission_type ?? 'emergency';
        $pdf->Cell(45, 6, ucfirst($admissionType), 'B', 0, 'L');
        $pdf->Ln(8);
        
        $pdf->Cell(50, 6, 'Room:', 0, 0, 'L');
        $room = $admission->room_number ?? '101';
        $pdf->Cell(45, 6, $room, 'B', 0, 'L');
        $pdf->Cell(25, 6, 'Attending Physician:', 0, 0, 'L');
        $physician = $admission->attending_physician ?? 'Dr. Maria Santos';
        $pdf->Cell(45, 6, $physician, 'B', 0, 'L');
        $pdf->Ln(15);
    }
    
    private static function addTwoColumnLayout($pdf, $soaData)
    {
        // Get data
        $charges = isset($soaData->itemized_charges) ? $soaData->itemized_charges : [];
        $payments = isset($soaData->payment_history) ? $soaData->payment_history : [];
        $totals = isset($soaData->totals) ? $soaData->totals : (object)[];
        
        // Calculate totals
        $totalCharges = $totals->current_charges ?? 9000;
        $totalPayments = $totals->current_payments ?? 3000;
        $outstandingBalance = $totals->outstanding_balance ?? ($totalCharges - $totalPayments);
        
        // Left Column - Itemized Charges
        $startY = $pdf->GetY();
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(95, 8, 'ITEMIZED CHARGES', 1, 0, 'L');
        
        // Right Column - Payment History (start at same Y position)
        $pdf->SetXY(105, $startY);
        $pdf->Cell(90, 8, 'PAYMENT HISTORY', 1, 1, 'L');
        
        // Left table headers
        $pdf->SetY($pdf->GetY());
        $leftY = $pdf->GetY();
        
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(50, 6, 'Service / Description', 1, 0, 'C');
        $pdf->Cell(15, 6, 'Quantity', 1, 0, 'C');
        $pdf->Cell(20, 6, 'Unit Price', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Total Amount', 1, 0, 'C');
        $pdf->Cell(15, 6, 'Date', 1, 0, 'C');
        
        // Right table headers (Payment History)
        $pdf->SetXY(105, $leftY);
        $pdf->Cell(20, 6, 'Payment Date', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Payment Method', 1, 0, 'C');
        $pdf->Cell(20, 6, 'Reference No.', 1, 0, 'C');
        $pdf->Cell(15, 6, 'Amount', 1, 0, 'C');
        $pdf->Cell(10, 6, 'Received By', 1, 1, 'C');
        
        // Data rows
        $pdf->SetFont('helvetica', '', 8);
        $maxRows = max(count($charges), count($payments), 5); // Minimum 5 rows
        
        for ($i = 0; $i < $maxRows; $i++) {
            $currentY = $pdf->GetY();
            
            // Left side - Charges
            if (isset($charges[$i])) {
                $charge = is_array($charges[$i]) ? (object) $charges[$i] : $charges[$i];
                $pdf->Cell(50, 6, substr($charge->service_name ?? 'Medical Service', 0, 25), 1, 0, 'L');
                $pdf->Cell(15, 6, $charge->quantity ?? '1', 1, 0, 'C');
                $pdf->Cell(20, 6, '₱' . self::formatCurrency($charge->unit_price ?? $charge->total_price ?? 0), 1, 0, 'R');
                $pdf->Cell(25, 6, '₱' . self::formatCurrency($charge->total_price ?? 0), 1, 0, 'R');
                $pdf->Cell(15, 6, isset($charge->date) ? date('d/m/Y', strtotime($charge->date)) : date('d/m/Y'), 1, 0, 'C');
            } else {
                // Empty row
                $pdf->Cell(50, 6, '', 1, 0, 'L');
                $pdf->Cell(15, 6, '', 1, 0, 'C');
                $pdf->Cell(20, 6, '', 1, 0, 'R');
                $pdf->Cell(25, 6, '', 1, 0, 'R');
                $pdf->Cell(15, 6, '', 1, 0, 'C');
            }
            
            // Right side - Payments
            $pdf->SetXY(105, $currentY);
            if (isset($payments[$i])) {
                $payment = is_array($payments[$i]) ? (object) $payments[$i] : $payments[$i];
                $pdf->Cell(20, 6, date('d/m/Y', strtotime($payment->payment_date ?? date('Y-m-d'))), 1, 0, 'C');
                $pdf->Cell(25, 6, ucfirst($payment->payment_method ?? 'Cash'), 1, 0, 'L');
                $pdf->Cell(20, 6, substr($payment->reference_number ?? '', 0, 12), 1, 0, 'L');
                $pdf->Cell(15, 6, '₱' . self::formatCurrency($payment->amount ?? 0), 1, 0, 'R');
                $pdf->Cell(10, 6, substr($payment->received_by ?? 'Staff', 0, 8), 1, 1, 'L');
            } else {
                // Empty row
                $pdf->Cell(20, 6, '', 1, 0, 'C');
                $pdf->Cell(25, 6, '', 1, 0, 'L');
                $pdf->Cell(20, 6, '', 1, 0, 'L');
                $pdf->Cell(15, 6, '', 1, 0, 'R');
                $pdf->Cell(10, 6, '', 1, 1, 'L');
            }
        }
        
        // Total rows
        $currentY = $pdf->GetY();
        
        // Left side total
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(50, 8, 'TOTAL CHARGES', 1, 0, 'C');
        $pdf->Cell(15, 8, '', 1, 0, 'C');
        $pdf->Cell(20, 8, '', 1, 0, 'C');
        $pdf->Cell(25, 8, '₱' . self::formatCurrency($totalCharges), 1, 0, 'R');
        $pdf->Cell(15, 8, '', 1, 0, 'C');
        
        // Right side total
        $pdf->SetXY(105, $currentY);
        $pdf->Cell(20, 8, 'TOTAL PAYMENTS', 1, 0, 'C');
        $pdf->Cell(25, 8, '', 1, 0, 'C');
        $pdf->Cell(20, 8, '', 1, 0, 'C');
        $pdf->Cell(15, 8, '₱' . self::formatCurrency($totalPayments), 1, 0, 'R');
        $pdf->Cell(10, 8, '', 1, 1, 'C');
        
        $pdf->Ln(10);
        
        // Account Summary Box (Right side)
        $summaryY = $pdf->GetY();
        $pdf->SetXY(105, $summaryY);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(90, 8, 'ACCOUNT SUMMARY', 1, 1, 'C');
        
        $pdf->SetXY(105, $pdf->GetY());
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 6, 'Total Charges:', 0, 0, 'L');
        $pdf->Cell(30, 6, '₱' . self::formatCurrency($totalCharges), 0, 1, 'R');
        
        $pdf->SetXY(105, $pdf->GetY());
        $pdf->Cell(60, 6, 'Total Payments:', 0, 0, 'L');
        $pdf->Cell(30, 6, '₱' . self::formatCurrency($totalPayments), 0, 1, 'R');
        
        $pdf->SetXY(105, $pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(200, 0, 0); // Red color for outstanding balance
        $pdf->Cell(60, 8, 'OUTSTANDING BALANCE:', 0, 0, 'L');
        $pdf->Cell(30, 8, '₱' . self::formatCurrency($outstandingBalance), 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0); // Reset to black
        
        $pdf->Ln(5);
    }
    
    private static function addSignatureSection($pdf)
    {
        $pdf->Ln(15);
        $pdf->SetFont('helvetica', '', 9);
        
        // Get current position
        $y = $pdf->GetY();
        
        // Left side - Prepared by
        $pdf->SetXY(15, $y);
        $pdf->Cell(0, 5, 'Prepared by:', 0, 1);
        $pdf->Ln(15);
        $pdf->SetX(15);
        $pdf->Cell(70, 5, '_________________________', 0, 1);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'Billing Clerk / Accountant', 0, 1);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, '(Signature over printed name)', 0, 1);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'Date: _______________', 0, 1);
        
        // Right side - Acknowledged by
        $pdf->SetXY(110, $y);
        $pdf->Cell(0, 5, 'Acknowledged by:', 0, 1);
        $pdf->Ln(15);
        $pdf->SetX(110);
        $pdf->Cell(70, 5, '_________________________', 0, 1);
        $pdf->SetX(110);
        $pdf->Cell(0, 5, 'Patient / Authorized Representative', 0, 1);
        $pdf->SetX(110);
        $pdf->Cell(0, 5, '(Signature over printed name)', 0, 1);
        $pdf->SetX(110);
        $pdf->Cell(0, 5, 'Date: _______________', 0, 1);
        
        // Footer notes
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Important Notice:', 0, 1, 'L');
        $pdf->Cell(0, 5, '• This statement reflects all charges and payments as of ' . date('d/m/Y') . ' at ' . date('h:i A'), 0, 1, 'L');
        $pdf->Cell(0, 5, '• Please keep this statement for your records', 0, 1, 'L');
        $pdf->Cell(0, 5, '• For inquiries, please contact our billing department at (082) 123-4567', 0, 1, 'L');
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated by BCSystem • ' . date('F j, Y \a\t g:i A'), 0, 1, 'C');
    }
    
    private static function formatCurrency($amount)
    {
        return number_format($amount ?? 0, 2);
    }
    
    private static function calculateAge($dateOfBirth)
    {
        if (!$dateOfBirth) return '';
        $today = new \DateTime();
        $birth = new \DateTime($dateOfBirth);
        $age = $today->diff($birth)->y;
        return $age;
    }

    /**
     * Generate Referral PDF
     */
    public static function generateReferralPDF($referral, $facility = null)
    {
        // Create new PDF document
        $pdf = new \TCPDF();
        $pdf->SetCreator('BCSystem');
        $pdf->SetAuthor($facility->name ?? 'Health Care Facility');
        $pdf->SetTitle('Patient Referral Form');
        
        // Set margins and auto page break
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        // Add a page
        $pdf->AddPage();
        
        // Add header with facility information
        self::addReferralHeader($pdf, $facility);
        
        // Add referral content
        self::addReferralContent($pdf, $referral);
        
        // Add footer
        self::addReferralFooter($pdf);
        
        return $pdf;
    }
    
    private static function addReferralHeader($pdf, $facility = null)
    {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 4, 'REPUBLIC OF THE PHILIPPINES', 0, 1, 'C');
        
        // Facility name
        $facilityName = 'BIRTHING HOME';
        if ($facility && isset($facility->name) && !empty(trim($facility->name))) {
            $facilityName = strtoupper(trim($facility->name));
        }
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 5, $facilityName, 0, 1, 'C');
        
        // Subtitle
        $subtitle = '';
        if ($facility && isset($facility->address) && !empty(trim($facility->address))) {
            $subtitle = trim($facility->address);
        } elseif ($facility && isset($facility->description) && !empty(trim($facility->description))) {
            $subtitle = trim($facility->description);
        }
        
        if ($subtitle) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 4, $subtitle, 0, 1, 'C');
        }
        
        $pdf->Ln(4);
        
        // Document title
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 6, 'PATIENT REFERRAL FORM', 0, 1, 'C');
        
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(6);
    }
    
    private static function addReferralContent($pdf, $referral)
    {
        $pdf->SetFont('helvetica', '', 10);
        $lineHeight = 6;
        
        // Patient Information
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, $lineHeight, 'Patient Information', 1, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        if ($referral->patient) {
            $patientName = trim($referral->patient->first_name . ' ' . 
                          ($referral->patient->middle_name ? $referral->patient->middle_name . ' ' : '') . 
                          $referral->patient->last_name);
            $age = $referral->patient->date_of_birth ? self::calculateAge($referral->patient->date_of_birth) : 'N/A';
            $dob = $referral->patient->date_of_birth ? date('F j, Y', strtotime($referral->patient->date_of_birth)) : 'N/A';
            
            // Name and Phone
            $pdf->Cell(50, $lineHeight, 'Patient Name:', 0, 0, 'L');
            $pdf->Cell(60, $lineHeight, $patientName, 'B', 0, 'L');
            $pdf->Cell(20, $lineHeight, 'Phone:', 0, 0, 'L');
            $pdf->Cell(50, $lineHeight, $referral->patient->contact_number ?? '', 'B', 1, 'L');
            
            // DOB and Age  
            $pdf->Cell(50, $lineHeight, 'Date of Birth:', 0, 0, 'L');
            $pdf->Cell(60, $lineHeight, $dob, 'B', 0, 'L');
            $pdf->Cell(20, $lineHeight, 'Age:', 0, 0, 'L');
            $pdf->Cell(50, $lineHeight, $age . ' years', 'B', 1, 'L');
            
            // Gender
            $pdf->Cell(50, $lineHeight, 'Gender:', 0, 0, 'L');
            $pdf->Cell(130, $lineHeight, $referral->patient->gender ?? '', 'B', 1, 'L');
            
            // Address
            $pdf->Cell(50, $lineHeight, 'Address:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->patient->address ?? '', 'B', 'L');
        }
        
        $pdf->Ln(3);
        
        // Referral Information
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, $lineHeight, 'Referral Information', 1, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(50, $lineHeight, 'Referral Date:', 0, 0, 'L');
        $pdf->Cell(60, $lineHeight, date('F j, Y', strtotime($referral->referral_date)), 'B', 0, 'L');
        $pdf->Cell(20, $lineHeight, 'Time:', 0, 0, 'L');
        $pdf->Cell(50, $lineHeight, date('g:i A', strtotime($referral->referral_time)), 'B', 1, 'L');
        
        $pdf->Cell(50, $lineHeight, 'Urgency Level:', 0, 0, 'L');
        $pdf->Cell(60, $lineHeight, strtoupper($referral->urgency_level), 'B', 0, 'L');
        $pdf->Cell(20, $lineHeight, 'Status:', 0, 0, 'L');
        $pdf->Cell(50, $lineHeight, strtoupper($referral->status ?? 'PENDING'), 'B', 1, 'L');
        
        $pdf->Ln(3);
        
        // Facility Information
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, $lineHeight, 'Facility Information', 1, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, $lineHeight, 'From - Facility:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(50, $lineHeight, 'Facility:', 0, 0, 'L');
        $pdf->Cell(130, $lineHeight, $referral->referring_facility, 'B', 1, 'L');
        
        $pdf->Cell(50, $lineHeight, 'Physician:', 0, 0, 'L');
        $pdf->Cell(60, $lineHeight, $referral->referring_physician, 'B', 0, 'L');
        $pdf->Cell(20, $lineHeight, 'Contact:', 0, 0, 'L');
        $pdf->Cell(50, $lineHeight, $referral->referring_physician_contact ?? '', 'B', 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, $lineHeight, 'To - Facility:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(50, $lineHeight, 'Facility:', 0, 0, 'L');
        $pdf->Cell(130, $lineHeight, $referral->receiving_facility, 'B', 1, 'L');
        
        $pdf->Cell(50, $lineHeight, 'Physician:', 0, 0, 'L');
        $pdf->Cell(60, $lineHeight, $referral->receiving_physician ?? '', 'B', 0, 'L');
        $pdf->Cell(20, $lineHeight, 'Contact:', 0, 0, 'L');
        $pdf->Cell(50, $lineHeight, $referral->receiving_physician_contact ?? '', 'B', 1, 'L');
        
        $pdf->Ln(3);
        
        // Clinical Information
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, $lineHeight, 'Clinical Information', 1, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(50, $lineHeight, 'Reason for Referral:', 0, 0, 'L');
        $pdf->MultiCell(130, $lineHeight, $referral->reason_for_referral ?? 'N/A', 'B', 'L');
        
        if (!empty($referral->clinical_summary)) {
            $pdf->Cell(50, $lineHeight, 'Clinical Summary:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->clinical_summary, 'B', 'L');
        }
        
        if (!empty($referral->current_diagnosis)) {
            $pdf->Cell(50, $lineHeight, 'Current Diagnosis:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->current_diagnosis, 'B', 'L');
        }
        
        if (!empty($referral->relevant_history)) {
            $pdf->Cell(50, $lineHeight, 'Relevant History:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->relevant_history, 'B', 'L');
        }
        
        if (!empty($referral->current_medications)) {
            $pdf->Cell(50, $lineHeight, 'Current Medications:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->current_medications, 'B', 'L');
        }
        
        if (!empty($referral->allergies)) {
            $pdf->Cell(50, $lineHeight, 'Allergies:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->allergies, 'B', 'L');
        }
        
        if (!empty($referral->vital_signs)) {
            $pdf->Cell(50, $lineHeight, 'Vital Signs:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->vital_signs, 'B', 'L');
        }
        
        if (!empty($referral->laboratory_results)) {
            $pdf->Cell(50, $lineHeight, 'Laboratory Results:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->laboratory_results, 'B', 'L');
        }
        
        if (!empty($referral->imaging_results)) {
            $pdf->Cell(50, $lineHeight, 'Imaging Results:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->imaging_results, 'B', 'L');
        }
        
        if (!empty($referral->treatment_provided)) {
            $pdf->Cell(50, $lineHeight, 'Treatment Provided:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->treatment_provided, 'B', 'L');
        }
        
        $pdf->Ln(3);
        
        // Transfer Details
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, $lineHeight, 'Transfer Details', 1, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(50, $lineHeight, 'Patient Condition:', 0, 0, 'L');
        $pdf->Cell(60, $lineHeight, $referral->patient_condition ?? 'stable', 'B', 0, 'L');
        $pdf->Cell(20, $lineHeight, 'Transportation:', 0, 0, 'L');
        $pdf->Cell(50, $lineHeight, ucfirst(str_replace('_', ' ', $referral->transportation_mode ?? 'ambulance')), 'B', 1, 'L');
        
        if (!empty($referral->accompanies_patient)) {
            $pdf->Cell(50, $lineHeight, 'Accompanies Patient:', 0, 0, 'L');
            $pdf->Cell(130, $lineHeight, $referral->accompanies_patient, 'B', 1, 'L');
        }
        
        if (!empty($referral->equipment_required)) {
            $pdf->Cell(50, $lineHeight, 'Equipment Required:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->equipment_required, 'B', 'L');
        }
        
        if (!empty($referral->isolation_precautions)) {
            $pdf->Cell(50, $lineHeight, 'Isolation Precautions:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->isolation_precautions, 'B', 'L');
        }
        
        if (!empty($referral->anticipated_care_level)) {
            $pdf->Cell(50, $lineHeight, 'Anticipated Care Level:', 0, 0, 'L');
            $pdf->Cell(130, $lineHeight, $referral->anticipated_care_level, 'B', 1, 'L');
        }
        
        if (!empty($referral->expected_duration)) {
            $pdf->Cell(50, $lineHeight, 'Expected Duration:', 0, 0, 'L');
            $pdf->Cell(130, $lineHeight, $referral->expected_duration, 'B', 1, 'L');
        }
        
        if (!empty($referral->special_instructions)) {
            $pdf->Cell(50, $lineHeight, 'Special Instructions:', 0, 0, 'L');
            $pdf->MultiCell(130, $lineHeight, $referral->special_instructions, 'B', 'L');
        }
        
        // Insurance Information
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, $lineHeight, 'Insurance Information', 1, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(180, $lineHeight, $referral->insurance_information ?? 'N/A', 'B', 'L');
        
        // Emergency Contact
        if ($referral->family_contact_name || $referral->family_contact_phone || $referral->family_contact_relationship) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, $lineHeight, 'Emergency Contact', 1, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            $pdf->Cell(50, $lineHeight, 'Contact Name:', 0, 0, 'L');
            $pdf->Cell(60, $lineHeight, ($referral->family_contact_name ?? 'N/A'), 'B', 0, 'L');
            $pdf->Cell(20, $lineHeight, 'Phone:', 0, 0, 'L');
            $pdf->Cell(50, $lineHeight, ($referral->family_contact_phone ?? 'N/A'), 'B', 1, 'L');
            
            $pdf->Cell(50, $lineHeight, 'Relationship:', 0, 0, 'L');
            $pdf->Cell(130, $lineHeight, ($referral->family_contact_relationship ?? 'N/A'), 'B', 1, 'L');
        }
        
        // Additional Notes
        if (!empty($referral->notes)) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, $lineHeight, 'Additional Notes', 1, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell(180, $lineHeight, $referral->notes, 'B', 'L');
        }
        
        // Signatures
        $pdf->Ln(10);
        $pdf->Cell(90, $lineHeight, 'Referring Physician:', 0, 0, 'L');
        $pdf->Cell(90, $lineHeight, 'Receiving Physician:', 0, 1, 'L');
        
        $pdf->Cell(90, 15, '', 'B', 0, 'L');
        $pdf->Cell(90, 15, '', 'B', 1, 'L');
    }
    
    private static function addReferralFooter($pdf)
    {
        // Empty or minimal footer
    }

    /**
     * Generate PDF from HTML content
     */
    public function generatePDF($html)
    {
        $pdf = new \TCPDF();
        $pdf->SetCreator('BCSystem');
        $pdf->SetAuthor('Buhangin Health Center');
        $pdf->SetTitle('Discharge Document');
        
        // Set margins and auto page break
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        // Add a page
        $pdf->AddPage();
        
        // Write HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Return PDF as string
        return $pdf->Output('', 'S');
    }
}
