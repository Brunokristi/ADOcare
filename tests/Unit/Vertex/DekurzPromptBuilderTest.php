<?php

namespace Tests\Unit\Vertex;

use App\Services\Vertex\DekurzPromptBuilder;
use PHPUnit\Framework\TestCase;

class DekurzPromptBuilderTest extends TestCase
{
    public function test_builds_prompt_with_required_shape_and_input_json(): void
    {
        $builder = new DekurzPromptBuilder();

        $prompt = $builder->build([
            'diagnosis' => ['I10'],
            'nurse_diagnosis' => ['A110'],
            'epicrisis' => 'Test epicrisis',
            'care_plan' => 'Test care plan',
            'mobility' => ['I'],
            'expected_duration' => 'one_month',
            'procedures' => [['code' => '3439', 'frequency' => 'daily']],
        ]);

        $this->assertStringContainsString('Return only JSON in this exact shape', $prompt);
        $this->assertStringContainsString('"sections"', $prompt);
        $this->assertStringContainsString('"diagnosis"', $prompt);
        $this->assertStringContainsString('"I10"', $prompt);
    }

    public function test_builds_strict_prompt_with_parseable_json_instruction(): void
    {
        $builder = new DekurzPromptBuilder();

        $prompt = $builder->build([], true);

        $this->assertStringContainsString("starting with '{' and ending with '}'", $prompt);
    }
}
