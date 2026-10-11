// เปิด Preview Modal
window.WPMB_Modal.open('wpmb-preview-modal');

// แสดง Toast
window.WPMB_Toast.success('✅ บันทึกสำเร็จ');

// อัปเดต Real-time Report
window.WPMB_RTReport.update({
    code: 'IPZZ-846',
    status: 'done',
    steps: [
        { name: 'ตรวจ CODE', status: 'done' },
        { name: 'ค้นหา Media', status: 'done' }
    ],
    counts: { done: 5, total: 250 }
});