# Sentient Scenes

Create simple animated scenes in this playful demo of [Sentient Design](https://bigmedium.com/ideas/hello-sentient-design.html) and radically adaptive interfaces.

Demo: <https://bigmedium.com/labs/scenes/>

## Overview

Sentient Scenes is a simple demonstration of a radically adaptive interface that changes its colors, typography, animations, and overall mood to match the user's intent. Provide a text prompt to describe context, and Sentient Scenes morphs its interface into an animated scene starring an adventurous little square.

This project explores these themes of Sentient Design:

- **Intelligent interfaces beyond chat.**  Weave intelligence into the interface itself, enabling the UI's style, mood, and manner to change based on user intent.

- **Personality without anthropomorphism.** Use simple animation to suggest personality without pretending to be a pseudo-human persona.

- **Smart defaults with room to play.** Use the "nudge" UI design pattern to help new users get started with example prompts.

## Getting started

### Prerequisites
- Web server with PHP 5.6+ support
- OpenAI API key

### Installation

1. Clone this repository to your web server
2. Copy `config-sample.php` to `config.php`
3. Edit `config.php` to add your OpenAI API key

```sh
cp config-sample.php config.php
nano config.php  # Edit to add your API key
```
## Usage

1. Browse to `index.html` in a web browser
2. Click or tap one of the scene suggestion nudges, or type your own description in the text input field (e.g., "incredible hulk").
3. Watch as the square character animates according to your description, as the color and typeface change to match the mood.

## How it works

The project consists of an interface made out of HTML, CSS, and vanilla JavaScript. The UI communicates with OpenAI via a thin PHP web app, which responds to user prompts with a JSON object giving the client-side application the info it needs to update its UI.

The UI results are impactful, but it's simple stuff under the hood: Update CSS values for background and foreground color; update the text caption; and add a CSS animation to the square.

### The prompt

The instructions for choosing and formatting the values are handled in a single prompt inside the `generate-scene.php` file. The prompt starts like so:

> You are a scene generation assistant that creates animated stories. Your output defines a scene by controlling a character (a square div) that moves based on the user's description. The character always starts at the center of the viewport. You will provide the colors, font, animation, and text description that match the story's topic and mood.  
> 
> \#\# Response guidelines
> 
> Generate a single JSON object (no other text) with these exact properties to define the scene’s \*\*mood, movement, and atmosphere\*\*. Return only a valid JSON object, formatted exactly as shown. Do not include explanations, extra text, or markdown headers.
> 
> ```JSON
>  {
>   "background": "string (6-digit hex color for the scene background)",
>   "content": "string (6-digit hex color for character and text, must meet WCAG 2.1 AA contrast with background)",
>   "shadow": "string (valid CSS box-shadow value for atmosphere)",
>   "caption": "string (must start with a relevant emoji and end with '...')",
>   "font-family": "string (only standard web-safe fonts or generic families)",
>   "keyframes": "string (CSS @keyframes block using percentage-based transforms)",
>   "animation": "string (CSS animation property using 'infinite')",
>   "fallback": "string (one of: bounce, float, jitter, pulse, drift)"
> }
> ```

The prompt continues by giving specific technical and format guidance for the responses of each of those UI values. But it also gives some artistic/creative guidance for choosing animation patterns and typefaces. For example, for movement the prompt suggests:

> - **Action**: Large, dynamic movements (±30-45vw horizontally, ±25-40vh vertically). Create diagonal paths across all quadrants.
> - **Peaceful**: Gentle movements (±15-25vw, ±10-20vh). Use figure-8 or circular patterns.
> - **Suspense**: Small, rapid movements (±5-10vw, ±5-10vh) with unpredictable direction changes.
> - **Sleep/Dreamy**: Combine gentle scaling (0.8-1.2) with slow drifting (±15vw, ±15vh).
> - **Mystery**: Slow, winding paths within ±30vw/vh range. Use curves and gradual direction changes.
> - **Combat**: Sharp rotations + quick bursts in multiple directions (±20-40vw/vh range).
> - **Joyful**: Bouncy movements exploring ±25vw horizontally, ±30vh vertically.
> - **Fearful**: Erratic movements covering ±15-35vw/vh range. Quick retreats and advances.
> - **Flying**: Smooth diagonal glides across ±40vw/vh range. Mix directions and heights.
> - **Swimming**: Wavy movements (±30vw horizontal, ±20vh vertical).

### Prompt as requirements doc

This kind of prompt reveals new opportunities for designers of Sentient Design experiences to be involved in the engineering process in new ways. The prompt gives technical instruction but also creative guidance and business requirements. Crafting prompts for intelligent interfaces becomes a central part of the design process as much as (or more) than Figma work. Designers can and should do a ton of exploratory design work by talking to AI in tools like ChatGPT, Claude, and Gemini. Just like you draft and explore visual and interaction experiments in traditional design tools, you should also be an active part of drafting prompts to test open-ended behaviors for Sentient Design experiences.

## Development and debugging

To enable debugging, set the `DEBUG` flag to `true` in `script.js`:

```javascript
const DEBUG = true;
```

When enabled, the console shows color-coded information for each scene request:

- **Requests**: Shows what text was sent to the API
- **Responses**: Displays the returned scene data (colors, animation, caption, etc.)
- **Token Usage**: Shows API token consumption and estimated costs
- **Errors**: Captures any issues that occur during processing

This information helps with troubleshooting and provides insight into how AI transforms text descriptions into visual scenes.

## Credits

Design and Development: [Josh Clark](https://bigmedium.com/about/josh-clark.html) and [Big Medium](https://bigmedium.com/)

## License

This project is licensed under the MIT License. Copyright © 2025 Josh Clark and Big Medium.

## About Sentient Design

[Sentient Design](https://bigmedium.com/ideas/hello-sentient-design.html) is the already-here future of intelligent interfaces: AI-mediated experiences that feel almost self-aware in their response to user needs. Sentient Design moves past static info and presentation to embrace UX as a radically adaptive story. These experiences are conceived and compiled in real time based on your intent in the moment—experiences that adapt to people, instead of forcing the reverse.

Sentient Design describes not only the form of this new user experience but also a framework and a philosophy for working with machine intelligence as design material. As our interfaces become more mindful, so must designers.

The Sentient Design framework was created by [Josh Clark](https://bigmedium.com/about/josh-clark.html) and [Veronika Kindred](https://bigmedium.com/about/veronika-kindred.html). Josh and Veronika are also authors of [Sentient Design](https://rosenfeldmedia.com/books/sentient-design/), the forthcoming book from Rosenfeld Media.


