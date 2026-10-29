<?php
$file = 'c:/xampp/htdocs/poultry/poultrychat/activities.php';
$content = file_get_contents($file);

$start = strpos($content, '<!-- Tasks Tab -->');
$end = strpos($content, '<script>', $start);

if ($start !== false && $end !== false) {
    // We want to keep the closing </div> of container-fluid etc, so let's just strip everything between <!-- Tasks Tab --> and </div> </div> \n <script>
    // Wait, the easiest way is to find exactly where to cut.
    // The previous div structure before <!-- Tasks Tab --> was closed properly. So we just remove the entire block.
    
    // We want to remove the block but keep the final closing </div>.
    // Let's just find the last </div> before <script> and see if it's needed.
    
    // Actually, in the layout:
    // <div class="container-fluid">
    //     <div class="row g-4 align-items-stretch">
    //          <div col-4></div>
    //          <div col-8></div>
    //     </div>
    // </div>
    // So there are 2 closing divs needed at the end!
    
    $clean_html = "        </div>\n    </div>\n</div>\n\n<script>";
    
    $content = substr_replace($content, $clean_html, $start, ($end + strlen('<script>')) - $start);
    file_put_contents($file, $content);
    echo "Removed successfully.\n";
} else {
    echo "Could not find tags.\n";
}
