        @page {
            size: A4;
            margin: 12mm 14mm 14mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 16pt;
            margin: 0;
            color: #000;
        }

        strong {
            font-weight: 700;
        }

        .header {
            width: 100%;
            margin-bottom: 8pt;
        }

        .header td {
            vertical-align: middle;
            padding: 0;
        }

        .header .logo {
            text-align: right;
        }

        .header .logo img {
            max-width: 300px;
            max-height: none;
        }

        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: 2pt solid #000;
            margin-bottom: 10pt;
        }

        .layout-table > tbody > tr > td {
            vertical-align: top;
            padding: 0;
            width: 50%;
        }

        .inner-box {
            padding: 6pt 8pt;
        }

        .inner-bordered {
            border: 1pt solid #000;
            padding: 6pt 8pt;
        }

        .section {
            border: 1pt solid #000;
            padding: 6pt 8pt;
            margin-bottom: 10pt;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 4pt;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            text-align: left;
            padding: 3pt 4pt;
            vertical-align: top;
        }

        table.data thead th {
            border-bottom: 1pt solid #000;
            font-weight: 700;
        }

        table.data tbody tr.position-row td {
            padding-top: 4pt;
            padding-bottom: 4pt;
            border-bottom: 0.5pt solid #999;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        .summary {
            width: 100%;
            margin-top: 6pt;
        }

        .summary td {
            padding: 3pt 4pt;
        }

        .summary .label {
            text-align: right;
            font-weight: 700;
        }

        .summary .value {
            text-align: right;
            font-weight: 700;
            width: 100pt;
        }

        .rechnungshinweis {
            border: 1pt solid #000;
            padding: 6pt 8pt;
            margin-bottom: 10pt;
            text-align: center;
        }

        .verlauf {
            font-size: 9pt;
        }

        .verlauf div {
            margin-bottom: 2pt;
        }

        .small {
            font-size: 8.5pt;
            color: #444;
        }

        .freitext {
            margin-top: 4pt;
            padding: 3pt 6pt;
            border-left: 2pt solid #000;
            font-size: 9pt;
        }

        tr.keep,
        .signature {
            page-break-inside: avoid;
        }

        .signature {
            width: 100%;
            margin-top: 22pt;
        }

        .signature td {
            width: 50%;
            vertical-align: top;
            padding: 0 12pt 0 0;
        }

        .sign-line {
            border-bottom: 0.6pt solid #000;
            min-height: 18mm;
            margin-bottom: 3pt;
        }
