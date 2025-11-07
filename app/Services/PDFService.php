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
        // Top banner with government header
        $pdf->SetFillColor(240, 248, 255); // Light blue background
        $pdf->Rect(10, 10, 190, 35, 'F');
        
        $pdf->SetY(15);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'REPUBLIC OF THE PHILIPPINES', 0, 1, 'C');
        
        // Facility name - larger and bold
        $facilityName = 'birthCareInfo.name';
        if ($facility && isset($facility->name) && !empty(trim($facility->name))) {
            $facilityName = strtoupper(trim($facility->name));
        }
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 6, $facilityName, 0, 1, 'C');
        
        // Subtitle with description or "Health Care Services"
        $subtitle = 'Health Care Services';
        if ($facility && isset($facility->description) && !empty(trim($facility->description))) {
            $subtitle = trim($facility->description);
        }
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 100, 0); // Dark green
        $pdf->Cell(0, 5, $subtitle, 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0); // Reset to black
        
        $pdf->Ln(8);
        
        // Document title with decorative lines
        $pdf->SetLineWidth(0.5);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, 'PATIENT REFERRAL FORM', 0, 1, 'C');
        
        $pdf->Line(20, $pdf->GetY() + 2, 190, $pdf->GetY() + 2);
        $pdf->Ln(8);
    }
    
    private static function addReferralContent($pdf, $referral)
    {
        // URGENT/EMERGENCY indicator if applicable
        if (in_array($referral->urgency_level, ['urgent', 'emergency', 'critical'])) {
            $pdf->SetFillColor(255, 240, 240); // Light red background
            $pdf->SetTextColor(200, 0, 0); // Dark red text
            $pdf->SetFont('helvetica', 'B', 12);
            $urgencyText = '⚠ ' . strtoupper($referral->urgency_level) . ' REFERRAL ⚠';
            if ($referral->urgency_level === 'critical') {
                $urgencyText = '🔴 CRITICAL REFERRAL - IMMEDIATE ATTENTION REQUIRED 🔴';
            }
            $pdf->Rect(15, $pdf->GetY(), 180, 10, 'F');
            $pdf->Cell(0, 10, $urgencyText, 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0); // Reset to black
            $pdf->Ln(5);
        }
        
        // Patient Information Section
        self::addTableSection($pdf, 'Patient Information', function($pdf) use ($referral) {
            if ($referral->patient) {
                $patientName = trim($referral->patient->first_name . ' ' . 
                              ($referral->patient->middle_name ? $referral->patient->middle_name . ' ' : '') . 
                              $referral->patient->last_name);
                
                $age = $referral->patient->date_of_birth ? self::calculateAge($referral->patient->date_of_birth) : 'N/A';
                $dob = $referral->patient->date_of_birth ? date('F j, Y', strtotime($referral->patient->date_of_birth)) : 'N/A';
                $phone = $referral->patient->phone_number ?? 'N/A';
                $gender = $referral->patient->gender ?? 'N/A';
                $address = $referral->patient->address ?? 'N/A';
                
                // Row 1: Name and Phone
                self::drawTableRow($pdf, [
                    ['label' => 'Patient Name:', 'value' => $patientName, 'width' => 95],
                    ['label' => 'Phone:', 'value' => $phone, 'width' => 85]
                ]);
                
                // Row 2: DOB and Age
                self::drawTableRow($pdf, [
                    ['label' => 'Date of Birth:', 'value' => $dob, 'width' => 95],
                    ['label' => 'Age:', 'value' => $age . ' years', 'width' => 85]
                ]);
                
                // Row 3: Gender
                self::drawTableRow($pdf, [
                    ['label' => 'Gender:', 'value' => $gender, 'width' => 95],
                    ['label' => '', 'value' => '', 'width' => 85]
                ]);
                
                // Row 4: Address (full width)
                self::drawTableRow($pdf, [
                    ['label' => 'Address:', 'value' => $address, 'width' => 180]
                ]);
            }
        });
        
        // Referral Information Section
        self::addTableSection($pdf, 'Referral Information', function($pdf) use ($referral) {
            $referralDate = date('F j, Y', strtotime($referral->referral_date));
            $referralTime = date('g:i A', strtotime($referral->referral_time));
            $urgencyLevel = strtoupper($referral->urgency_level);
            $status = strtoupper($referral->status ?? 'PENDING');
            
            // Row 1: Date and Time
            self::drawTableRow($pdf, [
                ['label' => 'Referral Date:', 'value' => $referralDate, 'width' => 95],
                ['label' => 'Time:', 'value' => $referralTime, 'width' => 85]
            ]);
            
            // Row 2: Urgency and Status
            self::drawTableRow($pdf, [
                ['label' => 'Urgency Level:', 'value' => $urgencyLevel, 'width' => 95],
                ['label' => 'Status:', 'value' => $status, 'width' => 85]
            ]);
        });
        
        // Facility Information Section
        self::addTableSection($pdf, 'Facility Information', function($pdf) use ($referral) {
            // From - Facility row
            self::drawSectionHeader($pdf, 'From - Facility:', 180);
            self::drawTableRow($pdf, [
                ['label' => 'Facility:', 'value' => $referral->referring_facility, 'width' => 180]
            ]);
            
            // From - Physician and Contact
            self::drawTableRow($pdf, [
                ['label' => 'Physician:', 'value' => $referral->referring_physician, 'width' => 95],
                ['label' => 'Contact:', 'value' => $referral->referring_physician_contact ?? '', 'width' => 85]
            ]);
            
            // To - Facility row
            self::drawSectionHeader($pdf, 'To - Facility:', 180);
            self::drawTableRow($pdf, [
                ['label' => 'Facility:', 'value' => $referral->receiving_facility, 'width' => 180]
            ]);
            
            // To - Physician and Contact
            self::drawTableRow($pdf, [
                ['label' => 'Physician:', 'value' => $referral->receiving_physician ?? '', 'width' => 95],
                ['label' => 'Contact:', 'value' => $referral->receiving_physician_contact ?? '', 'width' => 85]
            ]);
        });
        
        // Clinical Information Section
        self::addTableSection($pdf, 'Clinical Information', function($pdf) use ($referral) {
            // Reason for referral (full width)
            self::drawTableRow($pdf, [
                ['label' => 'Reason for referral:', 'value' => $referral->reason_for_referral, 'width' => 180, 'multiline' => true]
            ]);
            
            // Clinical Summary (full width if exists)
            if (!empty($referral->current_diagnosis)) {
                self::drawTableRow($pdf, [
                    ['label' => 'Clinical Summary:', 'value' => $referral->current_diagnosis, 'width' => 180, 'multiline' => true]
                ]);
            }
            
            // Current Diagnosis and Vital Signs
            if (!empty($referral->current_diagnosis) && !empty($referral->vital_signs)) {
                self::drawTableRow($pdf, [
                    ['label' => 'Current Diagnosis:', 'value' => $referral->current_diagnosis, 'width' => 95],
                    ['label' => 'Vital Signs:', 'value' => $referral->vital_signs, 'width' => 85]
                ]);
            } elseif (!empty($referral->vital_signs)) {
                self::drawTableRow($pdf, [
                    ['label' => 'Vital Signs:', 'value' => $referral->vital_signs, 'width' => 180]
                ]);
            }
        });
        
        // Transfer Details Section
        self::addTableSection($pdf, 'Transfer Details', function($pdf) use ($referral) {
            // Patient condition and Transportation
            $patientCondition = $referral->patient_condition ?? 'stable';
            $transportation = ucfirst(str_replace('_', ' ', $referral->transportation_mode ?? 'ambulance'));
            
            self::drawTableRow($pdf, [
                ['label' => 'Patient Condition:', 'value' => $patientCondition, 'width' => 95],
                ['label' => 'Transportation:', 'value' => $transportation, 'width' => 85]
            ]);
            
            // Special Instructions (full width if exists)
            if (!empty($referral->special_instructions)) {
                self::drawTableRow($pdf, [
                    ['label' => 'Special Instructions:', 'value' => $referral->special_instructions, 'width' => 180, 'multiline' => true]
                ]);
            }
        });
        
        // Emergency Contact Section (if available)
        if ($referral->family_contact_name || $referral->family_contact_phone) {
            self::addTableSection($pdf, 'Emergency Contact', function($pdf) use ($referral) {
                $contactName = $referral->family_contact_name ?? 'N/A';
                $contactPhone = $referral->family_contact_phone ?? 'N/A';
                $relationship = $referral->family_contact_relationship ?? 'N/A';
                
                // Name and Phone
                self::drawTableRow($pdf, [
                    ['label' => 'Contact Name:', 'value' => $contactName, 'width' => 95],
                    ['label' => 'Phone:', 'value' => $contactPhone, 'width' => 85]
                ]);
                
                // Relationship
                self::drawTableRow($pdf, [
                    ['label' => 'Relationship:', 'value' => $relationship, 'width' => 180]
                ]);
            });
        }
        
        // Signatures Section
        self::addTableSection($pdf, 'Signatures', function($pdf) use ($referral) {
            // Referring Physician
            self::drawTableRow($pdf, [
                ['label' => 'Referring Physician:', 'value' => $referral->referring_physician ?? '', 'width' => 95],
                ['label' => 'Date:', 'value' => '', 'width' => 85]
            ]);
            
            // Receiving Physician
            self::drawTableRow($pdf, [
                ['label' => 'Receiving Physician:', 'value' => $referral->receiving_physician ?? '', 'width' => 95],
                ['label' => 'Date:', 'value' => '', 'width' => 85]
            ]);
        });
    }
    
    private static function addReferralFooter($pdf)
    {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Generated by BCSystem on ' . date('F j, Y \a\t g:i A'), 0, 1, 'C');
    }
    
    /**
     * Helper functions for table-based layout
     */
    private static function addTableSection($pdf, $title, $contentCallback)
    {
        // Section header with gray background
        self::drawSectionHeader($pdf, $title, 180);
        
        // Execute the content callback
        $contentCallback($pdf);
        
        // Add some space after section
        $pdf->Ln(3);
    }
    
    private static function drawSectionHeader($pdf, $title, $width)
    {
        $pdf->SetFillColor(220, 220, 220); // Light gray background
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        self::drawCell($pdf, 15, $pdf->GetY(), $width, 10, $title, 1, 'L', true);
        $pdf->Ln(10);
    }
    
    private static function drawTableRow($pdf, $columns)
    {
        $startY = $pdf->GetY();
        $maxHeight = 0;
        $cellHeights = [];
        
        // First pass: calculate the height needed for each cell
        foreach ($columns as $index => $column) {
            if (empty($column['label']) && empty($column['value'])) {
                $cellHeights[$index] = 18; // Default height for empty cells
                continue;
            }
            
            $isMultiline = isset($column['multiline']) && $column['multiline'];
            $cellHeight = self::calculateCellHeight($pdf, $column, $isMultiline);
            $cellHeights[$index] = $cellHeight;
            $maxHeight = max($maxHeight, $cellHeight);
        }
        
        $currentX = 15; // Start position
        
        // Second pass: draw all cells with the same height
        foreach ($columns as $index => $column) {
            if (empty($column['label']) && empty($column['value'])) {
                $currentX += $column['width'];
                continue;
            }
            
            $isMultiline = isset($column['multiline']) && $column['multiline'];
            self::drawTextCell($pdf, $currentX, $startY, $column['width'], $maxHeight, $column['label'], $column['value'], $isMultiline);
            $currentX += $column['width'];
        }
        
        // Move to next row
        $pdf->SetY($startY + $maxHeight);
    }
    
    private static function calculateCellHeight($pdf, $column, $isMultiline = false)
    {
        $minHeight = 18; // Minimum cell height
        
        if ($isMultiline && !empty($column['value'])) {
            // Calculate height needed for multiline text
            $pdf->SetFont('helvetica', '', 9);
            $textWidth = $column['width'] - 4; // Account for padding
            $lines = self::getTextLines($pdf, $column['value'], $textWidth);
            $textHeight = count($lines) * 4; // 4 units per line
            return max($minHeight, $textHeight + 8); // Add padding
        }
        
        return $minHeight;
    }
    
    private static function getTextLines($pdf, $text, $width)
    {
        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';
        
        foreach ($words as $word) {
            $testLine = empty($currentLine) ? $word : $currentLine . ' ' . $word;
            $testWidth = $pdf->GetStringWidth($testLine);
            
            if ($testWidth <= $width) {
                $currentLine = $testLine;
            } else {
                if (!empty($currentLine)) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }
        
        if (!empty($currentLine)) {
            $lines[] = $currentLine;
        }
        
        return empty($lines) ? [''] : $lines;
    }
    
    private static function drawCell($pdf, $x, $y, $width, $height, $text, $border = 1, $align = 'L', $fill = false)
    {
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, $height, $text, $border, 0, $align, $fill);
    }
    
    private static function drawTextCell($pdf, $x, $y, $width, $height, $label, $value, $isMultiline = false)
    {
        // Draw border
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $y, $width, $height);
        
        $currentY = $y + 2; // Start with padding
        
        // Draw label if not empty
        if (!empty($label)) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY($x + 2, $currentY);
            $pdf->Cell($width - 4, 4, $label, 0, 0, 'L');
            $currentY += 6;
        }
        
        // Draw value if not empty
        if (!empty($value)) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            
            if ($isMultiline) {
                // Handle multiline text
                $lines = self::getTextLines($pdf, $value, $width - 4);
                foreach ($lines as $line) {
                    if ($currentY + 4 <= $y + $height - 2) { // Check if there's space
                        $pdf->SetXY($x + 2, $currentY);
                        $pdf->Cell($width - 4, 4, $line, 0, 0, 'L');
                        $currentY += 4;
                    }
                }
            } else {
                // Single line text
                $pdf->SetXY($x + 2, $currentY);
                $displayValue = $pdf->GetStringWidth($value) > ($width - 4) ? 
                    substr($value, 0, intval(($width - 4) * 0.35)) . '...' : $value;
                $pdf->Cell($width - 4, 4, $displayValue, 0, 0, 'L');
            }
        }
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
