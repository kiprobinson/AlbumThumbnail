AlbumThumbnail
==============

Generates a thumbnail image containing a somewhat artistic arrangement of four other images.

## Example usage:

    $at = new album_thumbnail();
    $at->add_image('/images/001.jpg');
    $at->add_image('/images/002.jpg');
    $at->add_image('/images/003.jpg');
    $at->add_image('/images/004.jpg');
    
    $at->make_thumbnail('/images/thumb1234.jpg');
