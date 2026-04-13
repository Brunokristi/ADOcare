<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildDekurzTrainingDataset extends Command
{
    protected $signature = 'ai:build-dekurz-dataset
        {--proposals=storage/app/private/proposals}
        {--dekurz=storage/app/private/dekurz}
        {--output=storage/app/private/ai-dataset-dekurz}';

    protected $description = 'Build proposal -> dekurz dataset';

    public function handle(): int
    {
        $proposalsDir = base_path($this->option('proposals'));
        $dekurzDir = base_path($this->option('dekurz'));
        $outputDir = base_path($this->option('output'));

        File::ensureDirectoryExists($outputDir);

        $proposals = $this->load($proposalsDir);
        $dekurz = $this->load($dekurzDir);

        $this->info('Proposals: ' . count($proposals));
        $this->info('Dekurz: ' . count($dekurz));

        $dekurzByPatient = [];

        foreach ($dekurz as $d) {
            $bn = $this->bn($d);
            if (!$bn) continue;

            $dekurzByPatient[$bn][] = $d;
        }

        $rows = [];

        foreach ($proposals as $proposal) {
            $bn = $this->bn($proposal);
            if (!$bn) continue;

            $matches = $dekurzByPatient[$bn] ?? [];
            if (empty($matches)) continue;

            // take first (or improve later)
            $d = $matches[0];

            $cleanProposal = $this->cleanProposal($proposal);
            $cleanDekurz = $this->cleanDekurz($d);

            if (empty($cleanDekurz['sections'])) continue;

            $rows[] = [
                'input_text' => $this->buildInput($cleanProposal),
                'output_text' => json_encode($cleanDekurz, JSON_UNESCAPED_UNICODE),
            ];
        }

        $this->writeJsonl($outputDir . '/train.jsonl', $rows);

        $this->info('Done. Rows: ' . count($rows));

        return self::SUCCESS;
    }

    protected function load($dir)
    {
        $items = [];

        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'json') continue;

            $json = json_decode(File::get($file), true);
            if (!$json) continue;

            $items[] = $json;
        }

        return $items;
    }

    protected function bn($doc)
    {
        return preg_replace('/[^0-9]/', '', $doc['patient_birth_number'] ?? '');
    }

    protected function cleanProposal($p)
    {
        return [
            'diagnosis' => $p['diagnosis'] ?? [],
            'nurse_diagnosis' => $p['nurse_diagnosis'] ?? [],
            'epicrisis' => $p['epicrisis'] ?? '',
            'care_plan' => $p['care_plan'] ?? '',
            'mobility' => $p['mobility'] ?? [],
            'expected_duration' => $p['expected_duration'] ?? '',
            'procedures' => $p['procedures'] ?? [],
        ];
    }

    protected function cleanDekurz($d)
    {
        $sections = [];

        foreach ($d['sections'] ?? [] as $s) {
            if (!empty($s['text'])) {
                $sections[] = [
                    'text' => trim($s['text']),
                ];
            }
        }

        return ['sections' => $sections];
    }

    protected function buildInput($proposal)
    {
        return implode("\n", [
            "You are given a structured nursing proposal.",
            "Generate likely dekurz section texts based on it.",
            "Return JSON only.",
            "",
            "INPUT JSON:",
            json_encode($proposal, JSON_UNESCAPED_UNICODE),
        ]);
    }

    protected function writeJsonl($path, $rows)
    {
        $lines = array_map(fn($r) => json_encode($r, JSON_UNESCAPED_UNICODE), $rows);
        File::put($path, implode("\n", $lines));
    }
}