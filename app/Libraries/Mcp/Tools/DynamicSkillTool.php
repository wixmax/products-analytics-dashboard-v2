<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;

class DynamicSkillTool implements ToolInterface
{
    protected array $skill;
    protected string $systemPrompt;

    public function __construct(array $skill, string $systemPrompt = '')
    {
        $this->skill = $skill;
        $this->systemPrompt = $systemPrompt;
    }

    public function getName(): string
    {
        $sId = $this->skill['id'] ?? 'custom-skill';
        return $this->skill['tool_name'] ?? ('get_' . str_replace('-', '_', $sId) . '_instructions');
    }

    public function getDescription(): string
    {
        $sId = $this->skill['id'] ?? 'custom-skill';
        return $this->skill['description'] ?? ('Retrieve skill instructions for ' . ($this->skill['title'] ?? $sId));
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_name' => [
                    'type'        => 'string',
                    'description' => 'Optional product name to customize prompt templates.'
                ],
                'product_image_url' => [
                    'type'        => 'string',
                    'description' => 'Optional product image reference URL for image-to-image lock.'
                ],
                'language' => [
                    'type'        => 'string',
                    'description' => 'Target language for copywriting and typography (e.g. Arabic, Moroccan Darija, French).'
                ]
            ],
            'additionalProperties' => false
        ];
    }

    public function execute(array $args, ?array $context = null): array
    {
        $sId = $this->skill['id'] ?? 'custom-skill';

        if ($sId === 'nano-banana-pro-consistent-ads') {
            $productName  = $args['product_name'] ?? '{{PRODUCT_NAME}}';
            $productImage = $args['product_image_url'] ?? '{{ask_user_product_image}}';
            $lang         = $args['language'] ?? 'Arabic';
            $content      = $this->skill['instructions'] ?? '';

            if (!empty($content)) {
                $content = str_replace('{{ask_user_product_image}}', $productImage, $content);
                $content = str_replace('{{LANGUAGE}}', $lang, $content);
                if ($productName !== '{{PRODUCT_NAME}}') {
                    $content = "# Target Product: {$productName}\n\n" . $content;
                }
                $instructions = $content;
            } else {
                $instructions = "# Nano Banana Pro Ad & Web Color Pipeline\n\n"
                              . "Product: {$productName}\n"
                              . "Reference Asset: {$productImage}\n"
                              . "Target Language: {$lang}\n";
            }
        } else {
            $instructions = $this->skill['instructions'] ?? $this->systemPrompt;
            if (!empty($args['product_name'])) {
                $instructions = "# Target Product: {$args['product_name']}\n\n" . $instructions;
            }
        }

        return [
            'status'             => 'success',
            'skill_id'           => $sId,
            'skill_name'         => $this->skill['title'] ?? $sId,
            'title'              => $this->skill['title'] ?? $sId,
            'badge'              => $this->skill['badge'] ?? 'AI Skill',
            'skill_instructions' => $instructions
        ];
    }
}
