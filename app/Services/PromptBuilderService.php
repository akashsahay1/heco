<?php

namespace App\Services;

use App\Models\AiPrompt;

class PromptBuilderService
{
    public function build(string $key, array $variables = []): ?array
    {
        $prompt = AiPrompt::where('key', $key)->where('is_active', true)->first();
        if (!$prompt) {
            return null;
        }

        $systemPrompt = $this->replacePlaceholders($prompt->system_prompt, $variables);
        $userPrompt = $this->replacePlaceholders($prompt->user_prompt_template, $variables);

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'model' => $prompt->model,
            'temperature' => (float) $prompt->temperature,
            'max_tokens' => $prompt->max_tokens,
            'response_format' => $prompt->response_format,
        ];
    }

    protected function replacePlaceholders(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }
}
