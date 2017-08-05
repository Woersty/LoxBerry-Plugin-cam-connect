<?
#
# Stream Pictures from IP-CAM as MJPEG Stream
#
# Code from here:
# http://ben-collins.blogspot.de/2010/06/php-sending-motion-jpeg.html
#

# Used to separate multipart
$boundary = "my_mjpeg";

# We start with the standard headers. PHP allows us this much
header("Cache-Control: no-cache");
header("Cache-Control: private");
header("Pragma: no-cache");
header("Content-type: multipart/x-mixed-replace; boundary=$boundary");

# From here out, we no longer expect to be able to use the header() function
print "--$boundary\n";

# Set this so PHP doesn't timeout during a long stream
set_time_limit(0);

# Disable Apache and PHP's compression of output to the client
@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);

# Set implicit flush, and flush all current buffers
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++)
    ob_end_flush();
ob_implicit_flush(1);

# The loop, producing one jpeg frame per iteration
while (true) {
    # Per-image header, note the two new-lines
    print "Content-type: image/jpeg\n\n";

    # Your function to get one jpeg image
    include ("index.php"); ?>

    # The separator
    print "--$boundary\n";
}
?>
