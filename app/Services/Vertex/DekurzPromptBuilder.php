<?php

namespace App\Services\Vertex;

class DekurzPromptBuilder
{
    /**
     * @param array<string, mixed> $proposal
     */
    public function build(array $proposal, bool $strict = false): string
    {
        $input = [
            'diagnosis' => is_array($proposal['diagnosis'] ?? null) ? $proposal['diagnosis'] : [],
            'nurse_diagnosis' => is_array($proposal['nurse_diagnosis'] ?? null) ? $proposal['nurse_diagnosis'] : [],
            'epicrisis' => (string) ($proposal['epicrisis'] ?? ''),
            'care_plan' => (string) ($proposal['care_plan'] ?? ''),
            'mobility' => is_array($proposal['mobility'] ?? null) ? $proposal['mobility'] : [],
            'expected_duration' => (string) ($proposal['expected_duration'] ?? ''),
            'procedures' => is_array($proposal['procedures'] ?? null) ? $proposal['procedures'] : [],
        ];

        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (! is_string($inputJson)) {
            throw new \RuntimeException('Nepodarilo sa vytvoriť vstup pre Dekurz AI.');
        }

        $base = "You are a nursing documentation assistant. "
            . "Generate likely draft outputs based on the provided input. "
            . "The output is only a draft suggestion for a nurse to review and edit. "
            . "Return JSON only.\n\n"
            . "You are given a structured nursing proposal. "
            . "Generate likely dekurz section texts based on it.\n"
            . "Return only JSON in this exact shape: "
            . "{\"sections\":[{\"text\":\"...\"}]}.\n"
            . "Use Slovak language. Keep medical terminology from input.\n"
            . "Do not output markdown, code fences, or explanations.\n\n"
            . "INPUT JSON:\n"
            . $inputJson;

        if (! $strict) {
            return $base;
        }

        return $base . "\n\nIMPORTANT: Return valid parseable JSON object only, starting with '{' and ending with '}'. Do not truncate output.";
    }
}
