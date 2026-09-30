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

namespace app\enum\common;

enum FormType: string
{
    case INPUT = 'Input';
    case INPUT_NUMBER = 'InputNumber';
    case SELECT = 'Select';
    case RADIO_GROUP = 'RadioGroup';
    case CHECKBOX_GROUP = 'CheckboxGroup';
    case API_SELECT = 'ApiSelect';
    case API_RADIO_GROUP = 'ApiRadioGroup';
//    case API_CHECKBOX_GROUP = 'ApiRadioGroup';
    case API_TREE_SELECT = 'ApiTreeSelect';
    case API_CASCADER = 'ApiCascader';
    case DICT = 'ApiDict';
    case DATE_PICKER = 'DatePicker';
    case UPLOAD = 'Upload';
    case EDITOR = 'Editor';
    case ICON_PICKER = 'IconPicker';

    /**
     * 获取人类可读的标签
     */
    public function label(): string
    {
         return match ($this) {
            self::INPUT => '单行文本',
            self::INPUT_NUMBER => '数字',
            self::SELECT => '下拉选择',
            self::RADIO_GROUP => '单选框',
            self::CHECKBOX_GROUP => '多选框',
            self::API_SELECT => '远程下拉',
            self::API_RADIO_GROUP => '远程单选',
//            self::API_CHECKBOX_GROUP => '远程多选',
            self::API_TREE_SELECT => '远程树选择',
            self::API_CASCADER => '远程级联',
            self::DICT => '字典组件',
            self::DATE_PICKER => '日期选择',
            self::UPLOAD => '文件上传',
            self::EDITOR => '富文本编辑器',
            self::ICON_PICKER => '图标选择器',
        };
    }

    /**
     * 获取对应的组件名称
     */
    public function component(): string
    {
        return match ($this) {
            self::INPUT => 'Input',
            self::INPUT_NUMBER => 'InputNumber',
            self::SELECT => 'Select',
            self::RADIO_GROUP => 'RadioGroup',
            self::CHECKBOX_GROUP => 'CheckboxGroup',
            self::API_SELECT => 'ApiSelect',
            self::API_RADIO_GROUP => 'ApiRadioGroup',
//            self::API_CHECKBOX_GROUP => 'ApiCheckboxGroup',
            self::API_TREE_SELECT => 'ApiTreeSelect',
            self::API_CASCADER => 'ApiCascader',
            self::DICT => 'ApiDict',
            self::DATE_PICKER => 'DatePicker',
            self::UPLOAD => 'Upload',
            self::EDITOR => 'Editor',
            self::ICON_PICKER => 'IconPicker',
        };
    }

    /**
     * 获取对应的颜色值
     */
    public function color(): string
    {
        return match ($this) {
            self::INPUT => '#409EFF',  // 蓝色
            self::INPUT_NUMBER => '#67C23A',  // 绿色
            self::SELECT => '#E6A23C',  // 橙色
            self::RADIO_GROUP => '#909399',  // 灰色
            self::CHECKBOX_GROUP => '#F56C6C',  // 红色
            self::API_SELECT => '#C06C84',  // 紫色
            self::API_RADIO_GROUP => '#6F42C1',  // 深紫色
//            self::API_CHECKBOX_GROUP => '#667EEA',  // 靛蓝色
            self::API_TREE_SELECT => '#3A8EE6',  // 亮蓝色
            self::API_CASCADER => '#409EFF',  // 蓝色
            self::DICT => '#13CE66',  // 浅绿色
            self::DATE_PICKER => '#FF8C42',  // 橙红色
            self::UPLOAD => '#FF4D4F',  // 暗红色
            self::EDITOR => '#722ED1',  // 紫红色
            self::ICON_PICKER => '#FAAD14',  // 金黄色
        };
    }
}