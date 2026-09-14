<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelTemplateFiller
{
    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<string>  $textColumns
     */
    public static function fill(
        string $templatePath,
        string $sheetName,
        int $dataStartRow,
        array $rows,
        array $textColumns = [],
    ): Spreadsheet {
        if (! is_file($templatePath)) {
            throw new RuntimeException('Report template not found.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (! $sheet) {
            throw new RuntimeException('Report template sheet not found.');
        }

        $lastCol = $sheet->getHighestColumn();
        $colCount = Coordinate::columnIndexFromString($lastCol);
        $sampleLast = max($sheet->getHighestRow(), $dataStartRow - 1);
        $rowCount = count($rows);
        $neededLast = $rowCount === 0 ? $dataStartRow - 1 : $dataStartRow + $rowCount - 1;

        if ($neededLast > $sampleLast) {
            $extra = $neededLast - $sampleLast;
            $sheet->insertNewRowBefore($sampleLast + 1, $extra);
            $sourceRange = 'A'.$dataStartRow.':'.$lastCol.$dataStartRow;
            for ($r = $sampleLast + 1; $r <= $neededLast; $r++) {
                $sheet->duplicateStyle($sheet->getStyle($sourceRange), 'A'.$r.':'.$lastCol.$r);
            }
        }

        foreach ($rows as $i => $values) {
            self::writeRow($sheet, $dataStartRow + $i, $values, $textColumns, $colCount);
        }

        if ($sampleLast > $neededLast) {
            $sheet->removeRow($neededLast + 1, $sampleLast - $neededLast);
        }

        $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($sheet));

        return $spreadsheet;
    }

    public static function download(Spreadsheet $spreadsheet, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary report file.');
        }
        @unlink($path);
        $path .= '.xlsx';

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<mixed>  $values
     * @param  list<string>  $textColumns
     */
    private static function writeRow(Worksheet $sheet, int $row, array $values, array $textColumns, int $colCount): void
    {
        for ($c = 1; $c <= $colCount; $c++) {
            $col = Coordinate::stringFromColumnIndex($c);
            $cell = $sheet->getCell($col.$row);
            $value = $values[$c - 1] ?? null;

            if (in_array($col, $textColumns, true)) {
                $cell->setValueExplicit((string) ($value ?? ''), DataType::TYPE_STRING);

                continue;
            }

            if ($value === null || $value === '') {
                $cell->setValue(null);

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);

                continue;
            }

            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }
    }
}
