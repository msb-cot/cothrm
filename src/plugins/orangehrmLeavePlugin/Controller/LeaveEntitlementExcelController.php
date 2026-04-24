<?php
 
namespace OrangeHRM\Leave\Controller;
 
use OrangeHRM\Core\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use OrangeHRM\Leave\Report\EmployeeLeaveEntitlementUsageReport;
use OrangeHRM\Leave\Dto\EmployeeLeaveEntitlementUsageReportSearchFilterParams;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
 
class LeaveEntitlementExcelController extends AbstractController
{
    public function execute(Request $request)
    {
        // 1️⃣ Build filter DTO
        $filterParams = new EmployeeLeaveEntitlementUsageReportSearchFilterParams();
 
        $filterParams->setEmpNumber(
            $request->query->getInt('empNumber')
        );
 
        $fromDate = $request->query->get('fromDate');
        $toDate = $request->query->get('toDate');
 
        if ($fromDate) {
            $filterParams->setFromDate(new \DateTime($fromDate));
        }
 
        if ($toDate) {
            $filterParams->setToDate(new \DateTime($toDate));
        }
 
        if ($request->query->get('leaveTypeId')) {
            $filterParams->setLeaveTypeId(
                $request->query->getInt('leaveTypeId')
            );
        }
 
        // 2️⃣ Get report data
        $report = new EmployeeLeaveEntitlementUsageReport();
        $reportDataObject = $report->getData($filterParams);
 
        // 3️⃣ Normalize data
        $rows = $reportDataObject->normalize();
 
        // ✅ Handle empty data safely
        if (empty($rows)) {
            $rows = [['No Records Found']];
        }
 
        // 4️⃣ Create Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
 
        // 5️⃣ Add logo (Azure-safe path)
        $drawing = new Drawing();
        $drawing->setName('Company Logo');
        $drawing->setDescription('Company Logo');
 
        $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/images/logo.png';
 
        if (file_exists($logoPath)) {
            $drawing->setPath($logoPath);
            $drawing->setHeight(60);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($sheet);
        }
 
        // 6️⃣ Headers
        $headers = [
            'Employee Name',
            'Leave From Date',
            'Leave To Date',
            'Leave Type',
            'Entitlement Days',
            'Pending Approval Days',
            'Scheduled Days',
            'Taken Days',
            'Balance Days'
        ];
 
        // Title
        $sheet->mergeCells('A8:I8');
        $sheet->setCellValue('A8', 'Employee Leave Report');
 
        $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A8')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
 
        // Header row
        $sheet->fromArray($headers, null, 'A10');
        $headerRange = 'A10:I10';
 
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFont()
            ->getColor()->setARGB(Color::COLOR_WHITE);
 
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
 
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0355A7');
 
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
 
        // Data
        $sheet->fromArray($rows, null, 'A11');
 
        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
 
        // OPTIONAL: hide columns (comment if issues)
         foreach (range('E', 'I') as $col) {
             $sheet->getColumnDimension($col)->setVisible(false);
         }
 
        // ✅ IMPORTANT: Clear output buffer (prevents corruption)
        if (ob_get_length()) {
            ob_end_clean();
        }
 
        // 7️⃣ Stream Excel (Azure + Docker safe)
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
 
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
 
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="leave_entitlement_usage.xlsx"'
        );
 
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->headers->set('Pragma', 'public');
 
        return $response;
    }
}
