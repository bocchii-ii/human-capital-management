<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Serif', Georgia, serif;
            background: #ffffff;
            color: #1a1a2e;
        }

        .page {
            width: 100%;
            height: 100vh;
            padding: 40px 60px;
            border: 12px solid #2c3e50;
            position: relative;
        }

        .inner-border {
            border: 3px solid #c0a060;
            padding: 30px 40px;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .logo-area {
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #2c3e50;
        }

        .title {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #c0a060;
            margin: 16px 0 8px;
        }

        .subtitle {
            font-size: 14px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 28px;
        }

        .presented-to {
            font-size: 13px;
            color: #777;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .recipient-name {
            font-size: 32px;
            font-style: italic;
            color: #1a1a2e;
            border-bottom: 2px solid #c0a060;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        .description {
            font-size: 14px;
            color: #444;
            line-height: 1.7;
            max-width: 500px;
            margin-bottom: 28px;
        }

        .course-name {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .meta-block {
            text-align: center;
            flex: 1;
        }

        .meta-label {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 13px;
            color: #333;
            font-weight: bold;
        }

        .cert-number {
            position: absolute;
            bottom: 60px;
            right: 90px;
            font-size: 9px;
            color: #bbb;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="inner-border">
            <div class="logo-area">
                <div class="company-name">WorkDay HCM</div>
            </div>

            <div class="title">Certificate</div>
            <div class="subtitle">of Completion</div>

            <div class="presented-to">This certifies that</div>
            <div class="recipient-name">
                {{ $certificate->employee->full_name }}
            </div>

            <div class="description">
                has successfully completed all required coursework for
            </div>

            <div class="course-name">{{ $certificate->course->title }}</div>

            <div class="meta-row">
                <div class="meta-block">
                    <div class="meta-label">Issued On</div>
                    <div class="meta-value">{{ $certificate->issued_at->format('F j, Y') }}</div>
                </div>
                <div class="meta-block">
                    <div class="meta-label">Certificate No.</div>
                    <div class="meta-value">{{ $certificate->certificate_number }}</div>
                </div>
                <div class="meta-block">
                    <div class="meta-label">Category</div>
                    <div class="meta-value">{{ ucfirst($certificate->course->category ?? 'General') }}</div>
                </div>
            </div>
        </div>

        <div class="cert-number">Verification: {{ $certificate->certificate_number }}</div>
    </div>
</body>
</html>
