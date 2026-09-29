<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تقرير العملاء - {{ $printDate }}</title>

    <style>

        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Tahoma, sans-serif;
            direction: rtl;
            color: #000;
            background: #fff;
            padding: 25px;
        }

        .print-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .report-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000;
        }

        .report-header h2 {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: bold;
        }

        .report-header .date {
            font-size: 13px;
            color: #333;
        }

        .report-filters {
            margin-top: 10px;
            font-size: 12px;
            color: #555;
        }

        .report-filters span {
            margin-left: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: middle;
        }

        th {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            background: #f0f0f0;
        }

        td {
            text-align: right;
            font-size: 12px;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        th:nth-child(1), td:nth-child(1) { width: 5%;  text-align: center; }
        th:nth-child(2), td:nth-child(2) { width: 22%; }
        th:nth-child(3), td:nth-child(3) { width: 14%; text-align: center; }
        th:nth-child(4), td:nth-child(4) { width: 22%; }
        th:nth-child(5), td:nth-child(5) { width: 17%; text-align: center; }
        th:nth-child(6), td:nth-child(6) { width: 12%; text-align: center; }

        .empty {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }

        .report-footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #000;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: bold;
        }

        @media print {

            body {
                padding: 10mm;
            }

            thead {
                display: table-header-group;
            }

            tbody {
                display: table-row-group;
            }

            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .report-header,
            .report-footer {
                page-break-inside: avoid;
            }
        }

    </style>

</head>

<body>

    <div class="print-container">

        <div class="report-header">

            <h2>تقرير العملاء</h2>

            <div class="date">
                تاريخ الطباعة: {{ $printDate }}
            </div>

            @if(!empty($filters))
                <div class="report-filters">
                    <strong>الفلاتر المطبقة:</strong>
                    @foreach($filters as $label => $value)
                        <span>{{ $label }}: {{ $value }}</span>
                    @endforeach
                </div>
            @endif

        </div>

        <table>

            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم العميل</th>
                    <th>رقم الهاتف</th>
                    <th>العنوان</th>
                    <th>رقم الحساب التحليلي</th>
                    <th>الحالة</th>
                </tr>
            </thead>

            <tbody>

                @forelse($customers as $customer)

                    <tr>
                        <td>{{ $loop->iteration }}</td>

                        <td>{{ $customer->CustomersName2 }}</td>

                        <td>{{ $customer->CusPhone ?: '—' }}</td>

                        <td>{{ $customer->CusAddress ?: '—' }}</td>

                        <td>{{ $customer->account?->accCode ?? '—' }}</td>

                        <td>
                            @if((int) $customer->is_active === 1)
                                نشط
                            @else
                                غير نشط
                            @endif
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="6" class="empty">
                            لا يوجد عملاء مطابقون لمعايير البحث
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

        <div class="report-footer">

            <span>إجمالي العملاء: {{ $totalCustomers }}</span>

            <span>العملاء النشطون: {{ $activeCustomers }}</span>

            <span>العملاء غير النشطين: {{ $inactiveCustomers }}</span>

        </div>

    </div>

</body>

</html>