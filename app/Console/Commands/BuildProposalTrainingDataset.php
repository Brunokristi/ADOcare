<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildProposalTrainingDataset extends Command
{
    protected $signature = 'ai:build-proposal-dataset
        {--documents=storage/app/private/scans/documents}
        {--proposals=storage/app/private/proposals}
        {--output=storage/app/private/ai-dataset}
        {--min-score=8}';

    protected $description = 'Build OCR -> proposal AI dataset';

    /** =========================
     * ENUMS
     * ========================= */

    private const ALLOWED_DIAGNOSIS_CODES = [
        'A0010','A039','A4600','C348','C3490','C4380','C44','C443','C4430','C502','C508','C61','C673','C6730','C774','D34',
        'E030','E1031','E1072','E1074','E1081','E11','E1172','E1174','E1180','E1190','E782','E7880','F252','F488','G0600',
        'G21','G553','I10','I1000','I109','I1090','I25','I250','I2500','I269','I3210','I482','I509','I614','I6140','I639',
        'I69','I693','I70','I7010','I7020','I7023','I7024','I7025','I709','I7420','I8011','I803','I83','I8300','I8320',
        'J2090','J410','J4100','J450','J90','K30','K3511','K7680','K92','L022','L0220','L024','L0240','L028','L029',
        'L0302','L0310','L0311','L033','L040','L08','L088','L0880','L720','L8907','L8914','L8915','L8916','L8917',
        'L8918','L8919','L8924','L8925','L8927','L8928','L8929','L97','L9700','L984','L9840','M100','M130','M1300',
        'M1380','M16','M1600','M1630','M17','M175','M179','M4216','M5010','M511','M54','M5400','M5403','M5404',
        'M5405','M5406','M5408','M5417','M5419','M544','M5450','M546','M5488','M5490','M8100','N083','N183',
        'N4180','N648','N6480','R104','R26','R262','R263','R2630','R63','R636','R6360','S2201','S3203','S3204',
        'S325','S5180','S7201','S7210','S7211','S723','S8180','T813','T8902'
    ];

    private const ALLOWED_PROCEDURE_CODES = [
        '3390','3392A','3393','3394','3395','3410','3413','3416','3419',
        '3422A','3422B','3422C','3423','3423A','3423B','3424',
        '3433','3436','3437','3439','3440'
    ];

    private const ALLOWED_FREQUENCIES = [
        'weekdays','weekends','daily','every_other_day',
        'three_times_weekly','twice_weekly','once_weekly',
        'twice_monthly','once_monthly','as_needed'
    ];

    private const ALLOWED_MOBILITY = ['H','I','F'];

    private const ALLOWED_DURATION = [
        'one_month','three_months','six_months','over_six_months'
    ];

    public function handle(): int
    {
        $documentsDir = base_path($this->option('documents'));
        $proposalsDir = base_path($this->option('proposals'));
        $outputDir = base_path($this->option('output'));

        File::ensureDirectoryExists($outputDir);

        $ocrDocs = $this->loadJsonFiles($documentsDir);
        $proposals = $this->loadJsonFiles($proposalsDir);

        $proposalsByBN = [];

        foreach ($proposals as $p) {
            $bn = $this->bn($p['patient_birth_number'] ?? null);
            if ($bn) {
                $proposalsByBN[$bn][] = $p;
            }
        }

        $rows = [];
        $skipped = [];

        foreach ($ocrDocs as $ocr) {

            $bn = $this->bn($ocr['patient_birth_number'] ?? null);
            $candidates = $proposalsByBN[$bn] ?? [];

            if (!$bn || empty($candidates)) {
                $skipped[] = ['reason' => 'no_candidates'];
                continue;
            }

            $proposal = $candidates[0]; // simple match

            $output = $this->clean($proposal);

            if (empty($output['diagnosis'])) {
                $skipped[] = ['reason' => 'missing_diagnosis'];
                continue;
            }

            $rows[] = [
                'input_text' => $this->buildInput($ocr),
                'output_text' => json_encode($output, JSON_UNESCAPED_UNICODE)
            ];
        }

        File::put($outputDir.'/train.jsonl', collect($rows)
            ->map(fn($r) => json_encode($r))
            ->implode("\n"));

        $this->info('Done: '.count($rows));

        return self::SUCCESS;
    }

    /** ========================= */

    private function clean(array $p): array
    {
        return [
            'diagnosis' => $this->dx($p['diagnosis'] ?? []),
            'nurse_diagnosis' => $this->dx($p['nurse_diagnosis'] ?? []),
            'epicrisis' => $this->str($p['epicrisis'] ?? ''),
            'care_plan' => $this->str($p['care_plan'] ?? ''),
            'mobility' => array_values(array_intersect(
                $this->arr($p['mobility'] ?? []),
                self::ALLOWED_MOBILITY
            )),
            'expected_duration' => in_array($p['expected_duration'] ?? '', self::ALLOWED_DURATION)
                ? $p['expected_duration']
                : '',
            'procedures' => $this->proc($p['procedures'] ?? [])
        ];
    }

    private function dx($items): array
    {
        $items = $this->arr($items);
        $out = [];

        foreach ($items as $i) {
            if (preg_match('/^[A-Z0-9]+/', $i, $m)) {
                if (in_array($m[0], self::ALLOWED_DIAGNOSIS_CODES)) {
                    $out[] = $m[0];
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function proc($items): array
    {
        $out = [];

        foreach ($items as $p) {
            $code = strtoupper($p['code'] ?? '');
            $freq = $p['frequency'] ?? '';

            if (!in_array($code, self::ALLOWED_PROCEDURE_CODES)) continue;

            if ($code === '3439') $freq = 'weekdays';
            if ($code === '3440') $freq = 'weekends';

            if (!in_array($freq, self::ALLOWED_FREQUENCIES)) continue;

            $out[] = compact('code','frequency');
        }

        return $out;
    }

    private function buildInput($ocr): string
    {
        return "You are given OCR output from a healthcare document.
Suggest likely values for nursing form fields.
Return JSON only.

OCR:
".json_encode([
            'patient_name'=>$ocr['patient_name'] ?? '',
            'text'=>$ocr['extracted_text'] ?? ''
        ], JSON_UNESCAPED_UNICODE);
    }

    /** helpers */

    private function loadJsonFiles($dir)
    {
        return collect(File::files($dir))
            ->map(fn($f)=>json_decode(File::get($f), true))
            ->filter()
            ->values()
            ->all();
    }

    private function bn($v)
    {
        return preg_replace('/\D/','',$v ?? '');
    }

    private function str($v)
    {
        return trim(preg_replace('/\s+/',' ',$v));
    }

    private function arr($v)
    {
        return is_array($v) ? $v : [$v];
    }
}