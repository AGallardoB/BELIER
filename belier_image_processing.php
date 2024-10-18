<?php
/* 
===========================================================================
===========================================================================
===========================================================================
					    IMAGE PROCESSING SUB-MODULE
						   (Release Candidate 2)
===========================================================================
===========================================================================
===========================================================================

An advancd rewrite of the image processing submodule. Functionality to create
URL's has been removed, as it may no longer be needed (it's available on
unused_funcs_scratchpad.php if it's needed). But it's much faster at doing
all the previous tasks, and should consume easily 1/5th the amount of RAM
by virtue of not using as many imagick objects.
*/
/*
===========================================================================
							CONTROL FLOW FUNCTION		
===========================================================================

	Following the KISS principle, i want these things to do as little as possible
    each. This simply handles getting called from the shim and orchestrates
    everything else.
*/
function belier_image_processing_main($image_params)
{
    // Enable maximum debugging.
    //error_reporting(E_ALL);
    //ini_set("display_errors", 1);

    // We may need the WP constants
    // Doesn't hurt to have it twice.
    if (!defined('ABSPATH')) {
        // Load the WP env if that is not the case.
        require_once('./wp-load.php');
    }
    // Let's handle calling our needed methods from here, starting with the preparations.
    // Generate our copy image in RAM, as well as data pertaining it's MIME type and dimensions
    // Let's save some RAM by not creating the image format variable, since we'll not
    // be creating files and giving them URL's to work on.
    //[$modified_image, $image_format, $image_dimensions] = belier_image_processing_prepwork($image_params);
    [$modified_image, $image_dimensions] = belier_image_processing_prepwork($image_params);
    // Tint our image using the data we've acquired earlier
    belier_image_processing_matrix_tint_application($modified_image, $image_params);
    // Print our text into the image
    belier_image_processing_print_text($modified_image, $image_dimensions, $image_params);
    // If the situation requires it... Create a filesystem copy so it can be printed.
    //belier_image_processing_create_files_urls($modified_image, $image_format);
    return ($modified_image);
}

/*
===========================================================================
						    INITIAL FILE PRE PWORK			
===========================================================================

	This function will create a copy of our filesystem image so it can be mangled around
    by the other methods, as we work on it.
*/
function belier_image_processing_prepwork($image_params): array
{
    // Load headers handler for safety.
    require_once(plugin_dir_path(__FILE__) . '/belier_headers.php');
    //Check the presence of the image file.
    $image_path = trailingslashit(wp_upload_dir()['basedir']) . $image_params['post_image'];
    if (!file_exists($image_path)) {
        //This check has been done by the shim, but in the event it's not, here it is again.
        belier_headers_nofile();
    }
    // Grab the selected image. For compositing and Sizing
    $modified_image = new Imagick($image_path);
    // Grab the current image format. We'll need this so we can properly substract RGB later.
    // $image_format = strtolower($modified_image->getImageFormat());
    // Force the format to be in lowercase, as imagick documentation is ambiguous. sometimes the format
    // appears in uppercase, others in lowercase. Safety braces.
    // $image_format = strtolower($image_format);
    // Array containing the X/Y dimensions of our image. This could've easily been two variables
    $image_dimensions = array('x' => $modified_image->getImageWidth(), 'y' => $modified_image->getImageHeight());
    // We aren't using the image format
    // return [$modified_image, $image_format, $image_dimensions];
    return [$modified_image, $image_dimensions];
}

/*
===========================================================================
							TINT  APPLICATION		
===========================================================================

	This really would be faster, save on resources, and get shit done without all the faff of
    the previous safe version was using. It seems to be an updated version of the RecolorImage
    function. It literally just changed name. Nobody just informed me THAT IT CHANGED NAMES
    If it doesn't work just grab the old methid from the original image_processing file
*/
function belier_image_processing_matrix_tint_application(&$modified_image, $image_params): void
{
    // This works because i believe it should work. It literally makes no sense, and i'm getting ideas from three different
    // sources for this to work. This is going to be hilarious if it actually works.
    // What model is this? RGBA with... Brightness Adjustment? Wonder if i could just do RGB? Why is this 6x6?
    // Sometime after this line, past future me somehow figured out a way to turn the 6x6 matrix into a 4x4?
    // ImageMagick does call this CYMKA/RGBA with Offsets.
    $colormatrix = [
        1.0, 0.0, 0.0, ($image_params['red'] / 255),
        0.0, 1.0, 0.0, ($image_params['green'] / 255),
        0.0, 0.0, 1.0, ($image_params['blue'] / 255),
        0.0, 0.0, 0.0, 1.0
    ];
    $modified_image->colorMatrixImage($colormatrix);
}

/*
===========================================================================
							TEXT APPLICATION		
===========================================================================

	I wonder if this could be shortened somehow. I'm sure i could just write directly
    to the image and save myself a heap of trouble. I'll definitely check.
*/
function belier_image_processing_print_text(&$modified_image, $image_dimensions, $image_params): void
{
    // Prepare a new image for text printing.
    $font_dir = trailingslashit(wp_upload_dir()['basedir']);
    $draw_text = new ImagickDraw();
    // Set our color for printing text, the font family, and the text size
    // smaller values will be a lot less intense.
    $text_colour = new ImagickPixel('rgba(128,128,128, 0.1)');
    $draw_text->setFillColor($text_colour);
    // Font needs to be placed in the wp-admin folder for some unknown reason.
    $draw_text->setFont($font_dir . '/Arvo-Regular.ttf');
    // Our font size. 30 seems good for me.
    $draw_text->setFontSize(30);
    // Write the text to the new image
    // Start at Y=0, Add 110px Vertically.
    for ($y_pos = 0; $y_pos <= $image_dimensions['y']; $y_pos += 110) {
        // On each new Y coordinate, position yourself on a random position outside the leftmost edge until
        // Start at X=0, add 185px Horizontally
        // We are going to iterate between pure black, gray, and white so text will work on a myriad of
        // different backgrounds... However it might be just changing things a line per line basis.
        $increment = 0;
        $alternating_text_colour = new ImagickPixel('rgba(' . (127 * $increment) . ',' . (127 * $increment) . ',' . (127 * $increment) . ', 0.1)');
        $draw_text->setFillColor($alternating_text_colour);
        for ($x_pos = 0; $x_pos <= $image_dimensions['x']; $x_pos += 185) {
            // Start writing text, using the parameters from $draw_text, over at our coordinates at 
            // x_pos, y_pos, with a 45 degree clockwise angle. The text is self explainatory: "..User_Name, 10.20.30.40, dd-mm-yyyy hh:mm:ss.."
            $modified_image->annotateImage($draw_text, $x_pos, $y_pos, 45, '..' . $image_params['username'] . ', ' . $image_params['ip_address'] . ', ' . $image_params['date_viewed'] . '..');
            // Increment our silly increment, and if it's less than two, reset it back to zero
            if ($increment != 2) {
                $increment = $increment + 1;
            } else {
                $increment = 0;
            }
        }
    }
}

/*
===========================================================================
						INLINE URL GENERATION		
===========================================================================

	Creates a filesystem file and generates the appropiate URLs.
    May this never be used

function belier_image_processing_create_files_urls(&$modified_image, &$image_format, &$image_params){
    // Prepare format for insertion on img tag. Perhaps i should throw an error if the format is not jpeg, gif, or png
    // As they're the supported formats for the IMG Tag... 
    if ($image_format==array('jp2', 'jpt', 'j2c', 'j2k', 'jxr', 'jpeg')){ $image_format='jpg'; }
    if ($image_format==array('png', 'png8', 'png00', 'png24', 'png32', 'png48', 'png64')){ $image_format='png'; }
    // Convert the image into a binary blob to be given to any IMG tag
    // Not used because it absolutely tanks clients. Keeping here just in case.
    // $modified_image_blob=base64_encode($modified_image->getImageBlob());
    // Prepare write to disk.
    // Create our destination path so we can nuke it later from the FileSystem
    $temp_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'belier-tmp';
    // Create the URL to our file so it can be echoed in an IMG
    $temp_url = trailingslashit( wp_upload_dir()['baseurl'] ) . 'belier-tmp';
    $RNG=rand(0,32000);
    // Append our filename to our URL or FS Path
    $output_file    = "{$temp_dir}/" . $RNG . "-" . str_ireplace("/", "-", $image_params['post_image']);
    $output_url     = "{$temp_url}/" . $RNG . "-" . str_ireplace("/", "-", $image_params['post_image']);
    // Create an empty file at our location with our given name.
    touch($output_file);
    //$fs_file=fopen($output_file, "w+");
    //fclose($fs_file);
    // Dump the contents of our Imagick object to our FS File
    file_put_contents($output_file, $modified_image);
    // PRINT THE THING IF WE'RE ACCESSING DIRECTLY
    //print $modified_image;
    //die();
    // Erase Imagick Object once done.
    $modified_image->clear();
    // Clear params array.
    unset($image_params);
    // Return our FS Path and URL through an array.
    return [$output_url, $output_file];
}
*/
?>