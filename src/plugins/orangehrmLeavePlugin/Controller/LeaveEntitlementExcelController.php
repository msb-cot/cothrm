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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use OrangeHRM\Leave\Report\EmployeeLeaveEntitlementUsageReport;
use OrangeHRM\Leave\Dto\EmployeeLeaveEntitlementUsageReportSearchFilterParams;

class LeaveEntitlementExcelController extends AbstractController
{
    public function execute(Request $request)
    {
        // ✅ Performance tuning (important for Azure)
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        // 1️⃣ Build filters
        $filterParams = new EmployeeLeaveEntitlementUsageReportSearchFilterParams();

        if ($request->query->get('empNumber')) {
            $filterParams->setEmpNumber($request->query->getInt('empNumber'));
        }

        if ($request->query->get('fromDate')) {
            $filterParams->setFromDate(new \DateTime($request->query->get('fromDate')));
        }

        if ($request->query->get('toDate')) {
            $filterParams->setToDate(new \DateTime($request->query->get('toDate')));
        }

        if ($request->query->get('leaveTypeId')) {
            $filterParams->setLeaveTypeId($request->query->getInt('leaveTypeId'));
        }

        // 2️⃣ Fetch data
        $report = new EmployeeLeaveEntitlementUsageReport();
        $data = $report->getData($filterParams)->normalize();

        if (empty($data)) {
            $data = [['No Records Found']];
        }

        // 3️⃣ Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 4️⃣ Add Logo (Azure-safe path)
        $logoPath = dirname(__DIR__, 4) . '/web/images/logo.png';

        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setPath($logoPath);
            $drawing->setHeight(60);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($sheet);
        }

        // 5️⃣ Title
        $sheet->mergeCells('A8:I8');
        $sheet->setCellValue('A8', 'Employee Leave Report');

        $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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

        $sheet->fromArray($headers, null, 'A10');

        $headerRange = 'A10:I10';

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0355A7'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // 7️⃣ Data
        $sheet->fromArray($data, null, 'A11');

        // 8️⃣ Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ❌ DO NOT hide columns in production (causes confusion)
         // OPTIONAL: hide columns (comment if issues)
         foreach (range('E', 'I') as $col) {
             $sheet->getColumnDimension($col)->setVisible(false);
         }

        // 9️⃣ Clean ALL output buffers (critical)
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 🔟 Stream response
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $fileName = 'leave_entitlement_usage_' . date('Ymd_His') . '.xlsx';

        $response->headers->set('Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response->headers->set('Content-Disposition',
            "attachment; filename=\"$fileName\"; filename*=UTF-8''$fileName");

        $response->headers->set('Cache-Control', 'max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Expires', '0');

        return $response;
    }
}