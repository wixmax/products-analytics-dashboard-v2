<?php

namespace App\Libraries\Mcp;

/**
 * SkillTemplates
 * Centralized loader and repository for AI Skill prompts to ensure Clean Architecture
 * and keep controllers lean (< 400 lines principle).
 */
class SkillTemplates
{
    /**
     * Get default Gemini Facebook Silent Ads & Voice-Over Skill prompt Markdown.
     */
    public static function getGeminiAdsVoiceoverPrompt(): string
    {
        $skillPath = ROOTPATH . '.agents/skills/gemini-facebook-product-ads-voiceover/SKILL.md';
        if (file_exists($skillPath)) {
            $content = file_get_contents($skillPath);
            if (!empty($content)) {
                return $content;
            }
        }

        return <<<'SKILL'
---
name: gemini-facebook-product-ads-voiceover
title: Gemini Facebook Silent Ads & Voice-Over Skill
version: 1.0.0
description: إنشاء حزمة إعلانية كاملة لـ Facebook & Instagram (فيديوهات UGC صامتة بدون حوار أو موسيقى + برومبتات تعليق صوتي TTS مستقلة ومتزامنة بدقة لـ Gemini 3.1 Flash TTS).
---

# Gemini Facebook Silent Ads & Voice-Over Skill
(Instructions loaded from .agents/skills/gemini-facebook-product-ads-voiceover/SKILL.md)
SKILL;
    }

    /**
     * Get default Gemini Facebook Product Ads Skill prompt Markdown.
     */
    public static function getGeminiAdsPrompt(): string
    {
        $skillPath = ROOTPATH . '.agents/skills/gemini-facebook-product-ads/SKILL.md';
        if (file_exists($skillPath)) {
            $content = file_get_contents($skillPath);
            if (!empty($content)) {
                return $content;
            }
        }

        return '';
    }
}
