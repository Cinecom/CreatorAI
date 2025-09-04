<?php
trait Creator_AI_Default_Variables {
    protected $default_prompts = array(
        'yta_prompt_article_system' => "You are an expert content writer specializing in SEO. Write a well-structured SEO article based on a YouTube video transcript. The article should be at least 1500 words. Ensure to include relevant keywords and actionable steps. Write in a friendly tone from the perspective of the video creator that is easy to read.\n\nYou must start the article with a 100-150 words paragraph summarizing the video transcript with key SEO terms, then continue with the rest of the content. When the transcript talks about a sponsor called audio, it's written as audiio and so is their website.\n\nCRITICAL STRUCTURE REQUIREMENTS:\n- Every heading MUST be followed by at least 2-3 paragraphs of relevant content (never put headings back-to-back)\n- Each paragraph should be 3-4 sentences long maximum for improved readability\n- Use a mix of H2 and H3 headings for proper hierarchy\n- Break up long explanations into multiple short paragraphs instead of one long paragraph\n\nEXTERNAL LINK REQUIREMENTS:\n- Include EXACTLY 3 external links that are highly relevant to the context\n- Each link MUST be from a COMPLETELY DIFFERENT domain\n- Never use the same domain more than once in the entire article\n- You must spread out the external links throughout the article, add one in the beginning, one in the middle and one on the end\n- Never place two links in the same paragraph\n- All links must be in paragraph text only (never in headings)\n- All links must open in a new tab using target=\"_blank\" rel=\"noopener noreferrer\"\n- Use descriptive, relevant anchor text for each link\n\nOUTPUT FORMAT:\nOutput everything in raw HTML code, but exclude the use of the <html>, <head>, <body> and other unnecessary tags. Start immediately from the <p> tag. Make use of <p>, <h2>, <h3>, <ul>, <li> and <strong> tags only. The hyperlinks should always open in a new tab and have a proper anchor text.",

        'yta_prompt_seo_system' => "You are an SEO specialist. Given an article text, write a meta description in 100–160 characters that focusses on important SEO keywords.",

        'yta_prompt_anchor' => "Given the following excerpt, generate a short SEO-friendly anchor text (3–10 words)",

        'yta_prompt_image_seo' => "Given the transcript context, generate a JSON object with keys 'alt_text' (a short, descriptive SEO-friendly phrase of 3–5 words) and 'caption' (a brief descriptive sentence) for the image.",

        'au_prompt_article' => "Write a detailed, SEO-optimized article about the software update. The article should:

            1. Start with an introduction explaining the significance of the update.
            2. Break down the major new features with clear H2 or H3 headings for each significant feature.
            3. Include detailed descriptions of each feature with examples of how users might benefit.
            4. Use proper HTML formatting with paragraphs, headings, and lists.
            5. Incorporate relevant keywords naturally throughout the text.
            6. End with a conclusion summarizing the update's importance and encouraging users to try the new version.

            The article should be 800-1500 words long, informative, and engaging. Write with an authoritative tone as if you are a technology expert familiar with this software. Do not mention the source of the information.",

        'au_ScrapingBee_JSON_prompt' => "The program version (use field 'version') and release date (use field 'release_date'). The new features (use field 'new_features'): title (use field 'title'), image link (use field 'image_link'), description (use field 'description') and link (use field 'link'). Return in JSON format"
    );
    protected $filler_paragraphs = [
        "This is an important aspect of the topic that deserves more attention. Understanding these details will help you master the concepts discussed.",
        "Let's explore this further to gain a complete understanding. The following details provide additional context and practical insights.",
        "This section covers key information relevant to the topic. Understanding these concepts will provide a solid foundation for what follows.",
        "Let's explore the details that make this topic important. The principles discussed here apply to many different scenarios and applications.",
        "To summarize the key points discussed above, these concepts are fundamental to mastering this subject. Understanding these principles will help you apply them effectively in your own projects.",
        "These insights provide valuable context and practical guidance for implementing these techniques in your workflows. Consider how they can be adapted to your specific needs."
    ];
}