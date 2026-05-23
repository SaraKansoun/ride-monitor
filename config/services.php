<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
        'frame_count' => (int) env('OPENAI_FRAME_COUNT', 3),
        'image_detail' => env('OPENAI_IMAGE_DETAIL', 'low'),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 500),
        'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffmpeg_timeout' => (int) env('FFMPEG_TIMEOUT', 60),
    ],

    'dashcam' => [
        'local_analysis_enabled' => env('AI_LOCAL_ANALYSIS_ENABLED', true),
        'demo_mode' => env('AI_DEMO_MODE', true),
        'openai_escalation_mode' => env('AI_OPENAI_ESCALATION_MODE', 'strict'),
        'local_confidence_threshold' => (float) env('AI_LOCAL_CONFIDENCE_THRESHOLD', 0.55),
        'openai_risk_threshold' => (float) env('AI_OPENAI_RISK_THRESHOLD', 0.65),
        'python_binary' => env('YOLO_PYTHON_BINARY', 'python'),
        'model_path' => env('YOLO_MODEL_PATH') ?: storage_path('app/ai-models/yolo11n.pt'),
        'script_path' => env('YOLO_ANALYZER_SCRIPT') ?: resource_path('ai/dashcam_analyzer.py'),
        'timeout' => (int) env('AI_LOCAL_ANALYSIS_TIMEOUT', 120),
        'demo_video_path' => env('AI_DEMO_VIDEO_PATH') ?: storage_path('app/demo-videos'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
