# TPAK DQ System v3 - WordPress Plugin

ปลั๊กอิน WordPress สำหรับระบบ Data Quality (DQ) ที่สามารถเชื่อมต่อกับ LimeSurvey และจัดการแบบสอบถามได้อย่างยืดหยุ่น

## คุณสมบัติหลัก

- เชื่อมต่อกับ LimeSurvey API
- จัดการแบบสอบถามแบบไดนามิก
- ระบบตรวจสอบคุณภาพข้อมูล
- Dashboard สำหรับแสดงผลและวิเคราะห์ข้อมูล
- ระบบรายงานอัตโนมัติ

## โครงสร้างโปรเจค

```
tpak-dq-systemv3/
├── tpak-dq-system.php          # ไฟล์หลักของปลั๊กอิน
├── includes/                    # ไฟล์ PHP classes และ functions
│   ├── class-tpak-dq-core.php
│   ├── class-limesurvey-api.php
│   ├── class-questionnaire-manager.php
│   ├── class-data-quality-checker.php
│   └── class-report-generator.php
├── admin/                       # ไฟล์สำหรับ admin panel
│   ├── class-admin.php
│   ├── views/
│   └── assets/
├── public/                      # ไฟล์สำหรับ frontend
│   ├── class-public.php
│   ├── views/
│   └── assets/
├── assets/                      # CSS, JS, Images
│   ├── css/
│   ├── js/
│   └── images/
├── languages/                   # ไฟล์ภาษา
├── tests/                       # Unit tests
└── docs/                        # เอกสาร
```

## การติดตั้ง

1. อัปโหลดโฟลเดอร์ `tpak-dq-system` ไปยัง `/wp-content/plugins/`
2. เปิดใช้งานปลั๊กอินผ่าน WordPress Admin
3. ตั้งค่า LimeSurvey API credentials
4. เริ่มต้นใช้งาน

## การพัฒนา

### ความต้องการระบบ
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- LimeSurvey API access

### การตั้งค่าสภาพแวดล้อมการพัฒนา
```bash
# Clone repository
git clone [repository-url]

# ติดตั้ง dependencies
composer install

# รัน tests
phpunit
```

## การใช้งาน

### การเชื่อมต่อ LimeSurvey
1. ไปที่ TPAK DQ System > Settings
2. กรอก LimeSurvey API URL และ credentials
3. ทดสอบการเชื่อมต่อ

### การจัดการแบบสอบถาม
1. ไปที่ TPAK DQ System > Questionnaires
2. เลือกแบบสอบถามจาก LimeSurvey
3. ตั้งค่าการตรวจสอบคุณภาพข้อมูล
4. เปิดใช้งานการตรวจสอบ

### การดูรายงาน
1. ไปที่ TPAK DQ System > Reports
2. เลือกช่วงเวลาและประเภทรายงาน
3. ดาวน์โหลดหรือดูรายงานออนไลน์

## การสนับสนุน

สำหรับคำถามหรือปัญหาการใช้งาน กรุณาติดต่อทีมพัฒนา TPAK

## License

GPL v2 หรือใหม่กว่า