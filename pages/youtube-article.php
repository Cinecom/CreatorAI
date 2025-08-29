<div class="wrap yt-article-wrap">
            <svg class="youtube-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z" fill="#FF0000"/>
            </svg>
        <h1 >YouTube Article Generator</h1>
    <p>
        Follow these steps:
        <ol>
            <li>Click the <strong>Fetch Videos</strong> button to load the latest videos from your selected YouTube channel.</li>
            <li>Once videos are loaded, use the <strong>Next</strong> and <strong>Previous</strong> buttons to navigate.</li>
            <li>Click on a video thumbnail to add <strong>images</strong> to your post and the <strong>A.I.</strong> will take care of the rest, including <strong>SEO!</strong></li>
        </ol>
    </p>
    <button id="ytarticle-fetch" class="button button-primary">Fetch Videos</button>
    <div id="ytarticle-videos" style="margin-top:20px;"></div>
    <div class="pagination-controls" style="margin-top:20px;">
        <button id="ytarticle-prev" class="button" style="display:none;">Previous</button>
        <button id="ytarticle-next" class="button" style="display:none;">Next</button>
    </div>
<?php if (method_exists($this, 'display_debug_panel')) { $this->display_debug_panel(); } ?>


</div>


