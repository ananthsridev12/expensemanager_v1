<?php
/**
 * Google Gemini API configuration for receipt scanning.
 *
 * 1. Go to https://aistudio.google.com/apikey and create a free API key.
 * 2. Paste it below — the free tier (gemini-1.5-flash) allows 15 req/min.
 * 3. This file is gitignored — never commit your key.
 */
return [
    'api_key' => '',               // <— paste your Gemini API key here
    'model'   => 'gemini-1.5-flash',
];
