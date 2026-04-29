<?php

namespace App\Bundle\MediaPipeline;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * UnidarMediaPipelineBundle
 * Automatically processes uploaded images: resize, compress, convert to WebP,
 * generate 3 responsive srcset sizes (320w, 640w, 1280w).
 */
class UnidarMediaPipelineBundle extends Bundle
{
}
