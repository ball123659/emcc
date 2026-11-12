<?php

// เติมข้อความภาษาไทยที่ Filament ยังไม่มี (ทับเฉพาะคีย์ที่ระบุ)
return array (
  'column_manager' => 
  array (
    'position' => 'ลำดับที่ :position จาก :count',
    'reordered' => 'ย้าย :column ไปลำดับที่ :position จาก :count',
    'actions' => 
    array (
      'move_up' => 
      array (
        'label' => 'เลื่อน :column ขึ้น',
      ),
      'move_down' => 
      array (
        'label' => 'เลื่อน :column ลง',
      ),
      'reorder' => 
      array (
        'label' => 'จัดลำดับคอลัมน์',
      ),
    ),
  ),
  'columns' => 
  array (
    'actions' => 
    array (
      'label' => 'การกระทำ',
    ),
    'editable' => 
    array (
      'saving' => 'กำลังบันทึก...',
      'saved' => 'บันทึกแล้ว',
      'failure' => 'ยืนยันการบันทึกไม่ได้ กรุณารีเฟรชหน้าแล้วลองใหม่',
      'unavailable' => 'รายการนี้แก้ไขไม่ได้แล้ว',
    ),
    'icon' => 
    array (
      'boolean' => 
      array (
        'true' => 'ใช่',
        'false' => 'ไม่ใช่',
      ),
    ),
    'select' => 
    array (
      'loading_message' => 'กำลังโหลด...',
      'no_options_message' => 'ไม่มีตัวเลือก',
      'no_search_results_message' => 'ไม่พบตัวเลือกที่ค้นหา',
      'placeholder' => 'เลือก',
      'searching_message' => 'กำลังค้นหา...',
      'search_prompt' => 'พิมพ์เพื่อค้นหา',
    ),
  ),
  'actions' => 
  array (
    'reorder_record' => 
    array (
      'label' => 'ลากเพื่อเรียงลำดับ',
    ),
    'move_record_up' => 
    array (
      'label' => 'เลื่อน :label ขึ้น',
    ),
    'move_record_down' => 
    array (
      'label' => 'เลื่อน :label ลง',
    ),
    'toggle_record_content' => 
    array (
      'label' => 'ขยาย/ยุบรายการ',
    ),
  ),
  'filters' => 
  array (
    'select' => 
    array (
      'relationship' => 
      array (
        'empty_option_label' => 'ไม่มี',
      ),
    ),
  ),
  'loading' => 'กำลังโหลด...',
  'reordering' => 
  array (
    'position' => ':label ลำดับที่ :position จาก :count',
    'moved' => 'ย้าย :label ไปลำดับที่ :position จาก :count',
  ),
  'result_count' => '{0} ไม่พบรายการ|[1,*] :count รายการ',
  'default_model_label' => 'รายการ',
);
