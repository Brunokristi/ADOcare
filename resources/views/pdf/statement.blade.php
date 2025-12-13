<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    .title { text-align:center; font-weight:bold; font-size:16px; margin-bottom:16px; }
    table { width:100%; border-collapse: collapse; }
    td { border:1px solid #000; padding:8px; vertical-align: top; }
  </style>
</head>
<body>
  <div class="title">SPRIEVODNÝ LIST</div>

  <table>
    <tr>
      <td style="text-align:right">
        Sprievodný list k: <strong>{{ $sheet['fileName'] }}</strong>
      </td>
    </tr>
    <tr>
      <td>
        <strong>Vykázaná suma:</strong><br>
        {{ number_format((float)$sheet['amount'], 2, ',', ' ') }} €
      </td>
    </tr>
    <tr>
      <td>
        <strong>Obdobie:</strong><br>
        {{ $sheet['periodFrom'] }} - {{ $sheet['periodTo'] }}
      </td>
    </tr>
  </table>

  <table>
    <tr>
      <td>
        <strong>Vykázal:</strong><br>
        {{ $sheet['performedBy'] }}
      </td>
      <td>
        <strong>Vykázané dňa:</strong><br>
        {{ $sheet['performedDate'] }}
      </td>
    </tr>
    <tr>
      <td>
        <strong>Spoločnosť:</strong><br>
        {{ $sheet['companyName'] }}
      </td>
      <td>
        <strong>Prevádzka:</strong><br>
        {{ $sheet['branchName'] }}
      </td>
    </tr>
  </table>
</body>
</html>
