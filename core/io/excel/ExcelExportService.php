<?php
declare(strict_types=1);

/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: http://www.madong.tech
 */
namespace core\io\excel;

use app\service\system\SysConfigService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\Container;

/**
 * Excel-vxe-table导出服务
 *
 * @author Mr.April
 * @since  1.0.
 */
class ExcelExportService
{

    /**
     * @param array    $params
     * @param callable $dataCallback
     *
     * @return array
     * @throws \Exception
     */
    public static function export(array $params, callable $dataCallback): array
    {
        try {
            // 验证字段配置
            $validFields = self::validateFields($params);

            // 初始化工作表
            [$spreadsheet, $sheet, $fieldMap] = self::initSpreadsheet($params, $validFields);

            // 生成表头
            $currentRow = self::generateHeader($sheet, $params, $fieldMap);

            // 分块写入数据
            $currentRow = self::writeData($spreadsheet, $sheet, $currentRow, $fieldMap, $dataCallback);

            // 保存文件
            $fileInfo = self::saveToDisk($spreadsheet, $params);

            // 生成下载链接
            $downloadInfo                        = self::generateDownloadInfo($fileInfo['path'], $params);
            $downloadInfo['params']['file_path'] = $fileInfo['download_path'];
            return [
                'url'    => $downloadInfo['url'] . '?file_path=' . $downloadInfo['params']['file_path'] . '&file=' . $downloadInfo['params']['file'],
                'expire' => $downloadInfo['expire'],
                'params' => $downloadInfo['params'],
            ];
        } catch (\Throwable $e) {
            throw  new \Exception('导出失败: ' . $e->getMessage());
        }
    }

    /**
     * 传入字段验证
     *
     * @param array $params
     *
     * @return array
     * @throws \Exception
     */
    private static function validateFields(array $params): array
    {
        $validFields = array_filter($params['fields'] ?? [], function ($field) {
            return !empty($field['field']) && !empty($field['title']);
        });
        if (empty($validFields)) {
            throw new \Exception("导出的字段配置无效：必须包含field和title");
        }
        return $validFields;
    }

    /**
     * 初始化工作表
     *
     * @param array $params
     * @param array $validFields
     *
     * @return array
     */
    private static function initSpreadsheet(array $params, array $validFields): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($params['sheetName'] ?? 'Sheet1', 0, 31));

        $fieldMap    = [];
        $columnIndex = 0;
        foreach ($validFields as $field) {
            $dbField            = $field['field'];
            $fieldMap[$dbField] = [
                'title'  => $field['title'],
                'column' => Coordinate::stringFromColumnIndex($columnIndex + 1),
                'format' => $field['format'] ?? null,
            ];
            $columnIndex++;
        }

        return [$spreadsheet, $sheet, $fieldMap];
    }

    /**
     * 动态表头
     *
     * @param $sheet
     * @param $params
     * @param $fieldMap
     *
     * @return int
     */
    private static function generateHeader($sheet, $params, $fieldMap): int
    {
        $currentRow = 1;
        if ($params['isHeader'] ?? true) {
            foreach ($fieldMap as $config) {
                $sheet->setCellValue($config['column'] . '1', $config['title']);
                self::setColumnStyle($sheet, $config); // 列宽及样式设置
            }
            $currentRow = 2;
        }
        return $currentRow;
    }

    /**
     * 动态样式列
     *
     * @param       $sheet
     * @param array $config
     */
    private static function setColumnStyle($sheet, array $config): void
    {
        switch ($config['format']['type'] ?? 'text') {
            case 'date':
                $sheet->getColumnDimension($config['column'])->setWidth(20);
                $sheet->getStyle($config['column'])
                    ->getNumberFormat()
                    ->setFormatCode('yyyy-mm-dd hh:mm:ss');
                break;
            case 'json':
                $sheet->getColumnDimension($config['column'])->setWidth(40);
                $sheet->getStyle($config['column'])
                    ->getAlignment()
                    ->setWrapText(true);
                break;
            default:
                $sheet->getColumnDimension($config['column'])->setAutoSize(true);
        }
    }

    /**
     * 分块写入数据
     *
     * @param          $spreadsheet
     * @param          $sheet
     * @param          $currentRow
     * @param          $fieldMap
     * @param callable $dataCallback
     *
     * @return int
     */
    private static function writeData($spreadsheet, $sheet, &$currentRow, $fieldMap, callable $dataCallback
    ): int
    {
        $dataCallback(function ($dataChunk) use (&$currentRow, $sheet, $fieldMap, $spreadsheet) {
            foreach ($dataChunk as $item) {
                foreach ($fieldMap as $dbField => $config) {
                    //这里可以扩展数据格式化以及公共枚举实现
                    $rawValue = data_get($item, $dbField);
                    if (is_array($rawValue) || is_object($rawValue)) {
                        $rawValue = json_encode($rawValue);
                    }
                    $sheet->setCellValue($config['column'] . $currentRow, $rawValue);
                }
                $currentRow++;

                // 内存优化：每处理500行释放内存
                if ($currentRow % 500 === 0) {
                    $spreadsheet->garbageCollect();
                }
            }
        });
        return $currentRow;
    }

    /**
     * 文件名安全过滤
     */
    private static function sanitizeFilename(string $name): string
    {
        return preg_replace('/[\/\\\?\*\[\]]/', '', $name);
    }

    /**
     * 保存到磁盘
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param array                                 $params
     *
     * @return array {path: string, filename: string}
     * @throws \Exception
     */
    private static function saveToDisk(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        array                                 $params
    ): array
    {
        $fileName = self::sanitizeFilename($params['filename'] ?? 'export_' . date('YmdHis'));
//        $path        = '/exports/' . date('Ym') . '/';
        $path        = '/exports/';//临时文件取消月份用完就删除
        $storagePath = runtime_path() . $path;

        // 目录创建带重试机制（增强健壮性）
        if (!is_dir($storagePath) && !mkdir($storagePath, 0755, true)) {
            $retry = 0;
            while ($retry++ < 3 && !mkdir($storagePath, 0755, true)) {
                usleep(100000); // 等待100ms重试
            }
            if (!is_dir($storagePath)) {
                throw new \Exception("目录创建失败: {$storagePath}");
            }
        }

        // 文件保存
        $writer   = new Xlsx($spreadsheet);
        $filePath = $storagePath . $fileName . '.xlsx';
        $writer->save($filePath);
        unset($spreadsheet); // 显式释放内存

        return [
            'path'          => $filePath,
            'filename'      => $fileName,
            'download_path' => $path . $fileName . '.xlsx',
        ];
    }

    /**
     * 生成下载信息
     *
     * @param string $filePath
     * @param array  $params
     *
     * @return array {url: string, expire: string}
     */
    private static function generateDownloadInfo(string $filePath, array $params): array
    {
        $systemConfig = Container::make(SysConfigService::class);
        $baseUrl      = rtrim($systemConfig->getConfig('site_url', 'site_setting'), '/');
        // 文件信息解析
        $fileInfo = pathinfo($filePath);
        $expire   = Carbon::today()->endOfDay()->timestamp;

        return [
            'url'    => "{$baseUrl}/export/download",
            'params' => [
                'file'   => $fileInfo['basename'],
                'expire' => $expire,
            ],
            'expire' => Carbon::createFromTimestamp($expire)->toDateTimeString(),
        ];
    }
}
