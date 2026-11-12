<?php

// เติมข้อความภาษาไทยที่ Filament ยังไม่มี (ทับเฉพาะคีย์ที่ระบุ)
return array (
  'checkbox_list' => 
  array (
    'required_description' => 'กรุณาเลือกอย่างน้อย 1 รายการ',
  ),
  'date_time_picker' => 
  array (
    'month_select' => 
    array (
      'label' => 'เดือน',
    ),
    'year_input' => 
    array (
      'label' => 'ปี',
    ),
    'hour_input' => 
    array (
      'label' => 'ชั่วโมง',
    ),
    'minute_input' => 
    array (
      'label' => 'นาที',
    ),
    'second_input' => 
    array (
      'label' => 'วินาที',
    ),
  ),
  'file_upload' => 
  array (
    'placeholder' => 'ยังไม่มีไฟล์',
    'actions' => 
    array (
      'download' => 
      array (
        'label' => 'ดาวน์โหลด',
      ),
      'open' => 
      array (
        'label' => 'เปิดในแท็บใหม่',
      ),
    ),
    'editor' => 
    array (
      'label' => 'แก้ไขรูป',
    ),
  ),
  'js_field' => 
  array (
    'failed_to_load' => 'โหลดช่องนี้ไม่สำเร็จ กรุณารีเฟรชหน้าแล้วลองใหม่',
  ),
  'repeater' => 
  array (
    'reordering' => 
    array (
      'item' => 'รายการ',
      'position' => ':label ลำดับที่ :position จาก :count',
      'moved' => 'ย้าย :label ไปลำดับที่ :position จาก :count',
    ),
    'columns' => 
    array (
      'actions' => 
      array (
        'label' => 'การกระทำ',
      ),
      'reorder' => 
      array (
        'label' => 'เรียงลำดับ',
      ),
    ),
  ),
  'rich_editor' => 
  array (
    'actions' => 
    array (
      'close_panel' => 
      array (
        'label' => 'ปิดแผง',
      ),
    ),
    'toolbar' => 
    array (
      'label' => 'แถบเครื่องมือจัดรูปแบบ',
    ),
    'tools' => 
    array (
      'h4' => 'หัวข้อระดับ 4',
      'h5' => 'หัวข้อระดับ 5',
      'h6' => 'หัวข้อระดับ 6',
      'paragraph' => 'ย่อหน้า',
    ),
  ),
  'select' => 
  array (
    'actions' => 
    array (
      'clear' => 
      array (
        'label' => 'ล้างที่เลือก',
      ),
      'remove_option' => 
      array (
        'label' => 'นำ :label ออก',
      ),
    ),
    'error_message' => 'โหลดตัวเลือกไม่สำเร็จ กรุณาลองใหม่',
    'required_description' => 'กรุณาเลือก 1 รายการ',
    'search_label' => 'ค้นหา',
  ),
  'toggle_buttons' => 
  array (
    'required_description' => 'กรุณาเลือกอย่างน้อย 1 รายการ',
  ),
);
