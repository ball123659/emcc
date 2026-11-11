{{-- ป้ายข้อความของช่องอัปโหลด (FilePond) ภาษาไทย — Filament ไม่มีภาษาไทยในตัว --}}
<script>
    document.addEventListener('FilePond:init', function (event) {
        var pond = event.detail && event.detail.pond;

        if (! pond || typeof pond.setOptions !== 'function') {
            return;
        }

        pond.setOptions({
            labelInvalidField: 'มีไฟล์ที่ไม่ถูกต้อง',
            labelFileWaitingForSize: 'กำลังตรวจขนาดไฟล์',
            labelFileSizeNotAvailable: 'ไม่ทราบขนาดไฟล์',
            labelFileLoading: 'กำลังโหลด',
            labelFileLoadError: 'โหลดไฟล์ไม่สำเร็จ',
            labelFileProcessing: 'กำลังอัปโหลด',
            labelFileProcessingComplete: 'อัปโหลดเสร็จแล้ว',
            labelFileProcessingAborted: 'ยกเลิกการอัปโหลดแล้ว',
            labelFileProcessingError: 'อัปโหลดไม่สำเร็จ',
            labelFileProcessingRevertError: 'ยกเลิกไฟล์ไม่สำเร็จ',
            labelFileRemoveError: 'ลบไฟล์ไม่สำเร็จ',
            labelTapToCancel: 'แตะเพื่อยกเลิก',
            labelTapToRetry: 'แตะเพื่อลองใหม่',
            labelTapToUndo: 'แตะเพื่อเอาออก',
            labelButtonRemoveItem: 'ลบ',
            labelButtonAbortItemLoad: 'ยกเลิก',
            labelButtonRetryItemLoad: 'ลองใหม่',
            labelButtonAbortItemProcessing: 'ยกเลิก',
            labelButtonUndoItemProcessing: 'เอาออก',
            labelButtonRetryItemProcessing: 'ลองใหม่',
            labelButtonProcessItem: 'อัปโหลด',
            labelMaxFileSizeExceeded: 'ไฟล์ใหญ่เกินไป',
            labelMaxFileSize: 'ขนาดสูงสุด {filesize}',
            labelMaxTotalFileSizeExceeded: 'ขนาดรวมเกินกำหนด',
            labelMaxTotalFileSize: 'ขนาดรวมสูงสุด {filesize}',
            labelFileTypeNotAllowed: 'ไม่รองรับไฟล์ชนิดนี้',
            fileValidateTypeLabelExpectedTypes: 'รองรับเฉพาะชนิดไฟล์ที่ระบุไว้ใต้ช่อง',
            imageValidateSizeLabelFormatError: 'ไม่รองรับรูปชนิดนี้',
            imageValidateSizeLabelImageSizeTooSmall: 'รูปเล็กเกินไป',
            imageValidateSizeLabelImageSizeTooBig: 'รูปใหญ่เกินไป',
        });
    });
</script>
