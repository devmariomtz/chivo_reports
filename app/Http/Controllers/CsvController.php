<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CsvController extends Controller
{

    public function uploadCsv(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,txt'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $file = $request->file('file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        // Ignorar la primera fila
        fgetcsv($handle);

        $doc = [];

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $doc[] = [
                // 'evento_id' => $row[0],
                'tiempo' => $row[1],
                // 'area' => $row[2],
                // 'dispositivo' => $row[3],
                // 'punto' => $row[4],
                // 'descripcion' => $row[5],
                'user_id' => $row[6],
                'nombre' => $row[7],
                'apellido' => $row[8],
                // 'tarjeta' => $row[9],
                'dpto_id' => $row[10],
                'dpto' => $row[11],
                // 'lector' => $row[12],
                'verificacion' => $row[13],
            ];
        }
        // eliminar la primera fila
        array_shift($doc);

        // elimiar de $doc los registros que no tengan el campo user_id
        $doc = array_filter($doc, function ($item) {
            return $item['user_id'] != '' && $item['user_id'] != 1 && $item['user_id'] != 2;
        });

        // verificar si el campo tiempo tiene la misma fecha en todos los registros
        $isDaily = count(array_unique(array_map(function ($item) {
            return date('Y-m-d', strtotime($item['tiempo']));
        }, $doc))) == 1;

        fclose($handle);
        return response(['data' => array_values($doc), 'isDaily' => $isDaily], 200);
    }

    public function downloadExcel(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'data' => 'required',
            'isDaily' => 'required',
            'firstEntry' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = $request->data;
        $isDaily = $request->isDaily;
        $firstEntry = $request->firstEntry;

        // agrupar por user_id
        $grouped = [];
        foreach ($data as $item) {
            $grouped[$item['user_id']][] = $item;
        }

        // verficar si el reporte es diario o semanal
        if ($request->isDaily) {
            $grouped = $this->dailyReport($grouped);
        } else {
            $grouped = $this->weeklyReport($grouped);
        }

        $this->builExcel($grouped, $isDaily, $firstEntry);
    }

    private function dailyReport($grouped)
    {
        // calcular de cada usuario la cantidad de horas registradas haciendo la resta del tiempo más grande con el más pequeño
        $grouped = array_map(function ($item) {
            $times = array_map(function ($row) {
                return strtotime($row['tiempo']);
            }, $item);
            $max = max($times);
            $min = min($times);
            $diff = $max - $min;
            // agregar a $grouped la cantidad de horas registradas, $max y $min
            return [
                'name' => $item[0]['nombre'] . ' ' . $item[0]['apellido'],
                'hours' => date('H:i:s', $diff),
                'max' => date('H:i:s', $max),
                'min' => date('H:i:s', $min),
                'n_day' => date('d', $max),
                'day_es' => $this->getDay(date('l', $max)),
                // posicion de la semana
                'position' => $this->getPosition(date('l', $max))
            ];
        }, $grouped);
        return $grouped;
    }

    private function weeklyReport($grouped)
    {
        // agrupar por día de semana los datos de cada usuario
        $grouped = array_map(function ($item) {
            $grouped = [];
            foreach ($item as $row) {
                $day = date('l', strtotime($row['tiempo']));
                $grouped[$day][] = $row;
            }
            return $grouped;
        }, $grouped);

        // calcular de cada día de la semana la cantida de horas registradas haciendo la resta del tiempo más grande con el más pequeño
        $grouped = array_map(function ($item) {
            $grouped = [];
            foreach ($item as $day => $rows) {
                $times = array_map(function ($row) {
                    return strtotime($row['tiempo']);
                }, $rows);
                $max = max($times);
                $min = min($times);
                $diff = $max - $min;
                // $hours = $diff / 3600; // en horas
                // agregar a $grouped el día de la semana y la cantidad de horas registradas, $max , $min y el día de la semana en español
                $grouped[] = [
                    'day' => $day,
                    'name' => $rows[0]['nombre'] . ' ' . $rows[0]['apellido'],
                    'hours' => date('H:i:s', $diff),
                    'max' => date('H:i:s', $max),
                    'min' => date('H:i:s', $min),
                    'n_day' => date('d', $max),
                    'day_es' => $this->getDay($day),
                    // posicion de la semana
                    'position' => $this->getPosition($day)
                ];
            }
            return $grouped;
        }, $grouped);

        // quitar los sabados y domingos
        $grouped = array_map(function ($item) {
            return array_filter($item, function ($day) {
                return $day['day'] != 'Saturday' && $day['day'] != 'Sunday';
            });
        }, $grouped);

        // reiniciar los punteros de los arrays
        $grouped = array_map(function ($item) {
            return array_values($item);
        }, $grouped);

        return $grouped;
    }

    private function builExcel($data, $isDaily, $firstEntry)
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        require '../vendor/autoload.php';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // El nombre del archivo debe de ser ReporteSemanal-fecha.xlsx
        $type = $isDaily ? 'ReporteDiario' : 'ReporteSemanal';
        $filename = $type . "-" . date('d-m-Y') . '.xlsx';

        // nombre de ka hoja activa
        $sheet->setTitle($type . '-' . date('d-m-Y'));

        // Info para los reportes
        $info = $this->getInfoForReports($isDaily, $data);
        $columsLetters = $info[0];
        $firtsUser = $info[1];

        // Headers
        $this->buildHeaders($firtsUser, $sheet, $columsLetters, $isDaily);

        // Body
        if ($isDaily) {
            $finalRow = $this->buildBodyDailyReport($data, $sheet, $columsLetters, $firstEntry);
        } else {
            $finalRow = $this->buildBodyWeeklyReport($data, $sheet, $columsLetters);
        }

        // Border
        $this->buildBorder($sheet, $finalRow, $columsLetters);

        $writer = new Xlsx($spreadsheet);
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment;filename=\"$filename\"");
        $writer->save("php://output");
        exit();
    }

    private function buildBorder($sheet, $finalRow, $columsLetters)
    {
        $sheet->getStyle('A1:' . $columsLetters[0] . ($finalRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function buildBodyDailyReport($data, $sheet, $columsLetters, $firstEntry)
    {
        $row = 2; // Initialize row counter
        foreach ($data as $user) {
            $sheet->setCellValue('A' . $row, $user['name']);
            if (!$firstEntry) {
                // une las filas para el nombre
                $sheet->mergeCells('A' . $row . ':A' . $row + 2);
                // asigna valores y estilo a la filas del ultimo marcaje y horas reportadas
                $sheet->setCellValue('B' . ($row + 1), 'Último Marcaje');
                $sheet->setCellValue('B' . ($row + 2), 'Horas Reportadas');
                $sheet->getStyle('B' . ($row + 2))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF002060');
                $sheet->getStyle('B' . ($row + 2))->getFont()->getColor()->setARGB('FFFFFFFF');
            }
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->setCellValue('B' . $row, 'Primer Marcaje');

            // asignar el valor de 0 en la posición de la semana ya que $columsLetters solo tiene una posición
            $user['position'] = 0;
            $this->buildCells($sheet, $row, $user, $columsLetters, $firstEntry);
            // sumar 3 al contador de filas si $firstEntry es false
            $row = $firstEntry ? $row + 1 : $row + 3;
        }
        return $row;
    }

    private function buildBodyWeeklyReport($data, $sheet, $columsLetters)
    {
        $row = 2; // Initialize row counter
        foreach ($data as $user) {
            // nombre del usuario
            $firstElement = reset($user);
            $sheet->setCellValue('A' . $row, $firstElement['name']);
            $sheet->mergeCells('A' . $row . ':A' . $row + 2);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            // partes del reporte
            $sheet->setCellValue('B' . $row, 'Primer Marcaje');
            $sheet->setCellValue('B' . ($row + 1), 'Último Marcaje');
            $sheet->setCellValue('B' . ($row + 2), 'Horas Reportadas');
            $sheet->getStyle('B' . ($row + 2))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF002060');
            $sheet->getStyle('B' . ($row + 2))->getFont()->getColor()->setARGB('FFFFFFFF');
            if (count($user) == 5) {
                $this->completeRegister($user, $sheet, $row, $columsLetters);
            } else {
                $this->incompleteRegister($user, $sheet, $row, $columsLetters);
            }

            $row = $row + 3;
        }
        return $row;
    }

    private function buildHeaders($firtsUser, $sheet, $columsLetters, $isDaily)
    {
        $sheet->setCellValue('A1', 'NOMBRE');
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->setCellValue('B1', 'REPORTE');
        $sheet->getColumnDimension('B')->setWidth(20);

        $sheet->getStyle('A1:' . $columsLetters[0] . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $columsLetters[0] . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF002060');
        $sheet->getStyle('A1:' . $columsLetters[0] . '1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:' . $columsLetters[0] . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        if ($isDaily) {
            $sheet->setCellValue('C1', $firtsUser['day_es'] . ' ' . $firtsUser['n_day']);
            $sheet->getColumnDimension('C')->setWidth(20);
            return;
        }

        $i = 0;
        foreach ($firtsUser as $day) {
            $sheet->setCellValue($columsLetters[$i] . '1', $day['day_es'] . ' ' . $day['n_day']);
            $sheet->getColumnDimension($columsLetters[$i])->setWidth(20);
            $i++;
        }
    }

    private function getInfoForReports($isDaily, $data)
    {
        if ($isDaily) {
            $columsLetters = ['C'];
            $firtsUser = reset($data);
            return [$columsLetters, $firtsUser];
        }

        // obtener un usuario de la data que tenga 5 registros para el reporte semanal
        foreach ($data as $user) {
            if (count($user) == 5) {
                $firtsUser = $user;
                break;
            }
        }
        // sino hay un usuario con 5 registros, agregar manualmente uno
        $firtsUser = $firtsUser ?? [
            ['day_es' => 'VIERNES', 'n_day' => ''],
            ['day_es' => 'JUEVES', 'n_day' => ''],
            ['day_es' => 'MIÉRCOLES', 'n_day' => ''],
            ['day_es' => 'MARTES', 'n_day' => ''],
            ['day_es' => 'LUNES', 'n_day' => '']
        ];
        $columsLetters = range('G', 'C');

        return [$columsLetters, $firtsUser];
    }

    private function getDay($day)
    {
        return [
            'Monday' => 'LUNES',
            'Tuesday' => 'MARTES',
            'Wednesday' => 'MIÉRCOLES',
            'Thursday' => 'JUEVES',
            'Friday' => 'VIERNES',
            'Saturday' => 'SÁBADO',
            'Sunday' => 'DOMINGO',
        ][$day];
    }

    private function getPosition($day)
    {
        return [
            'Saturday' => 6,
            'Sunday' => 5,
            'Monday' => 4,
            'Tuesday' => 3,
            'Wednesday' => 2,
            'Thursday' => 1,
            'Friday' => 0,
        ][$day];
    }

    private function buildCells($sheet, $row, $day, $columsLetters, $firstEntry = false)
    {
        $sheet->setCellValue($columsLetters[$day['position']] . $row, $day['min']);
        $sheet->getStyle($columsLetters[$day['position']] . $row . ':' . $columsLetters[$day['position']] . $row + 2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        if (!$firstEntry) {
            $sheet->setCellValue($columsLetters[$day['position']] . $row + 1, $day['max']);
            $sheet->setCellValue($columsLetters[$day['position']] . $row + 2, $day['hours']);
            $sheet->getStyle($columsLetters[$day['position']] . $row + 2)->getFont()->setBold(true);
            $sheet->getStyle($columsLetters[$day['position']] . ($row + 2))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF8ED973');
        }
    }

    private function completeRegister($user, $sheet, $row, $columsLetters)
    {
        for ($i = 0; $i < 5; $i++) {
            $day = $user[$i];
            $this->buildCells($sheet, $row, $day, $columsLetters);
        }
    }

    private function incompleteRegister($user, $sheet, $row, $columsLetters)
    {
        $usedCol = [];
        foreach ($user as $day) {
            $this->buildCells($sheet, $row, $day, $columsLetters);
            $usedCol[] = $day['position'];
        }
        rsort($usedCol);
        // Eliminar de $columsLetters los elementos que estan en $usedCol
        foreach ($usedCol as $col) {
            if (isset($columsLetters[$col])) {
                unset($columsLetters[$col]);
            }
        }
        // reiniciar los punteros de los arrays
        $emptyColumsLetters = array_values($columsLetters);
        // completar los registros faltantes
        for ($i = 0; $i < count($emptyColumsLetters); $i++) {
            $emptyDay = [
                'hours' => '00:00:00',
                'max' => '00:00:00',
                'min' => '00:00:00',
                'position' => $i
            ];
            $this->buildCells($sheet, $row, $emptyDay, $emptyColumsLetters);
        }
    }
}
