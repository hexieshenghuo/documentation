<!DOCTYPE html>
<html lang="en">
<head>
<title>Documentation - Point Cloud Library (PCL)</title>
</head>

<!DOCTYPE html>

<html>
  <head>
    <meta charset="utf-8" />
    <title>Robust pose estimation of rigid objects &#8212; PCL 0.0 documentation</title>
    <link rel="stylesheet" href="_static/sphinxdoc.css" type="text/css" />
    <link rel="stylesheet" href="_static/pygments.css" type="text/css" />
    <script id="documentation_options" data-url_root="./" src="_static/documentation_options.js"></script>
    <script src="_static/jquery.js"></script>
    <script src="_static/underscore.js"></script>
    <script src="_static/doctools.js"></script>
    <script src="_static/language_data.js"></script>
    <link rel="search" title="Search" href="search.php" />
<?php
define('MODX_CORE_PATH', '/var/www/pointclouds.org/core/');
define('MODX_CONFIG_KEY', 'config');

require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx = new modX();
$modx->initialize('web');

$snip = $modx->runSnippet("getSiteNavigation", array('id'=>5, 'phLevels'=>'sitenav.level0,sitenav.level1', 'showPageNav'=>'n'));
$chunkOutput = $modx->getChunk("site-header", array('sitenav'=>$snip));
$bodytag = str_replace("[[+showSubmenus:notempty=`", "", $chunkOutput);
$bodytag = str_replace("`]]", "", $bodytag);
echo $bodytag;
echo "\n";
?>
<div id="pagetitle">
<h1>Documentation</h1>
<a id="donate" href="http://www.openperception.org/support/"><img src="/assets/images/donate-button.png" alt="Donate to the Open Perception foundation"/></a>
</div>
<div id="page-content">

  </head><body>

    <div class="document">
      <div class="documentwrapper">
          <div class="body" role="main">
            
  <div class="section" id="robust-pose-estimation-of-rigid-objects">
<span id="alignment-prerejective"></span><h1>Robust pose estimation of rigid objects</h1>
<p>In this tutorial, we show how to find the alignment pose of a rigid object in a scene with clutter and occlusions.</p>
</div>
<div class="section" id="the-code">
<h1>The code</h1>
<p>First, download the test models: <a class="reference download internal" download="" href="_downloads/dc852161ec2a68913228bf115791669d/chef.pcd"><code class="xref download docutils literal notranslate"><span class="pre">object</span></code></a> and <a class="reference download internal" download="" href="_downloads/af8916511281ec88adb748f6b2164380/rs1.pcd"><code class="xref download docutils literal notranslate"><span class="pre">scene</span></code></a>.</p>
<p>Next, copy and paste the following code into your editor and save it as <code class="docutils literal notranslate"><span class="pre">alignment_prerejective.cpp</span></code> (or download the source file <a class="reference download internal" download="" href="_downloads/ccf619165cce84dd55bd0d2d46795435/alignment_prerejective.cpp"><code class="xref download docutils literal notranslate"><span class="pre">here</span></code></a>).</p>
<div class="highlight-cpp notranslate"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>  1
  2
  3
  4
  5
  6
  7
  8
  9
 10
 11
 12
 13
 14
 15
 16
 17
 18
 19
 20
 21
 22
 23
 24
 25
 26
 27
 28
 29
 30
 31
 32
 33
 34
 35
 36
 37
 38
 39
 40
 41
 42
 43
 44
 45
 46
 47
 48
 49
 50
 51
 52
 53
 54
 55
 56
 57
 58
 59
 60
 61
 62
 63
 64
 65
 66
 67
 68
 69
 70
 71
 72
 73
 74
 75
 76
 77
 78
 79
 80
 81
 82
 83
 84
 85
 86
 87
 88
 89
 90
 91
 92
 93
 94
 95
 96
 97
 98
 99
100
101
102
103
104
105
106
107
108
109
110
111
112
113
114
115
116
117
118
119
120
121
122
123</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="cp">#include</span> <span class="cpf">&lt;Eigen/Core&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/point_types.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/point_cloud.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/common/time.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/console/print.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/features/normal_3d_omp.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/features/fpfh_omp.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/filters/filter.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/filters/voxel_grid.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/io/pcd_io.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/registration/icp.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/registration/sample_consensus_prerejective.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/segmentation/sac_segmentation.h&gt;</span><span class="cp"></span>
<span class="cp">#include</span> <span class="cpf">&lt;pcl/visualization/pcl_visualizer.h&gt;</span><span class="cp"></span>

<span class="c1">// Types</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">PointNormal</span> <span class="n">PointNT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">PointCloud</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">PointCloudT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">FPFHSignature33</span> <span class="n">FeatureT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">FPFHEstimationOMP</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="p">,</span><span class="n">PointNT</span><span class="p">,</span><span class="n">FeatureT</span><span class="o">&gt;</span> <span class="n">FeatureEstimationT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">PointCloud</span><span class="o">&lt;</span><span class="n">FeatureT</span><span class="o">&gt;</span> <span class="n">FeatureCloudT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">visualization</span><span class="o">::</span><span class="n">PointCloudColorHandlerCustom</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">ColorHandlerT</span><span class="p">;</span>

<span class="c1">// Align a rigid object to a scene with clutter and occlusions</span>
<span class="kt">int</span>
<span class="nf">main</span> <span class="p">(</span><span class="kt">int</span> <span class="n">argc</span><span class="p">,</span> <span class="kt">char</span> <span class="o">**</span><span class="n">argv</span><span class="p">)</span>
<span class="p">{</span>
  <span class="c1">// Point clouds</span>
  <span class="n">PointCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">object</span> <span class="p">(</span><span class="k">new</span> <span class="n">PointCloudT</span><span class="p">);</span>
  <span class="n">PointCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">object_aligned</span> <span class="p">(</span><span class="k">new</span> <span class="n">PointCloudT</span><span class="p">);</span>
  <span class="n">PointCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">scene</span> <span class="p">(</span><span class="k">new</span> <span class="n">PointCloudT</span><span class="p">);</span>
  <span class="n">FeatureCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">object_features</span> <span class="p">(</span><span class="k">new</span> <span class="n">FeatureCloudT</span><span class="p">);</span>
  <span class="n">FeatureCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">scene_features</span> <span class="p">(</span><span class="k">new</span> <span class="n">FeatureCloudT</span><span class="p">);</span>
  
  <span class="c1">// Get input object and scene</span>
  <span class="k">if</span> <span class="p">(</span><span class="n">argc</span> <span class="o">!=</span> <span class="mi">3</span><span class="p">)</span>
  <span class="p">{</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_error</span> <span class="p">(</span><span class="s">&quot;Syntax is: %s object.pcd scene.pcd</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">argv</span><span class="p">[</span><span class="mi">0</span><span class="p">]);</span>
    <span class="k">return</span> <span class="p">(</span><span class="mi">1</span><span class="p">);</span>
  <span class="p">}</span>
  
  <span class="c1">// Load object and scene</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Loading point clouds...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="k">if</span> <span class="p">(</span><span class="n">pcl</span><span class="o">::</span><span class="n">io</span><span class="o">::</span><span class="n">loadPCDFile</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="p">(</span><span class="n">argv</span><span class="p">[</span><span class="mi">1</span><span class="p">],</span> <span class="o">*</span><span class="n">object</span><span class="p">)</span> <span class="o">&lt;</span> <span class="mi">0</span> <span class="o">||</span>
      <span class="n">pcl</span><span class="o">::</span><span class="n">io</span><span class="o">::</span><span class="n">loadPCDFile</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="p">(</span><span class="n">argv</span><span class="p">[</span><span class="mi">2</span><span class="p">],</span> <span class="o">*</span><span class="n">scene</span><span class="p">)</span> <span class="o">&lt;</span> <span class="mi">0</span><span class="p">)</span>
  <span class="p">{</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_error</span> <span class="p">(</span><span class="s">&quot;Error loading object/scene file!</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="k">return</span> <span class="p">(</span><span class="mi">1</span><span class="p">);</span>
  <span class="p">}</span>
  
  <span class="c1">// Downsample</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Downsampling...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">VoxelGrid</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">grid</span><span class="p">;</span>
  <span class="k">const</span> <span class="kt">float</span> <span class="n">leaf</span> <span class="o">=</span> <span class="mf">0.005f</span><span class="p">;</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">setLeafSize</span> <span class="p">(</span><span class="n">leaf</span><span class="p">,</span> <span class="n">leaf</span><span class="p">,</span> <span class="n">leaf</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">filter</span> <span class="p">(</span><span class="o">*</span><span class="n">object</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">filter</span> <span class="p">(</span><span class="o">*</span><span class="n">scene</span><span class="p">);</span>
  
  <span class="c1">// Estimate normals for scene</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Estimating scene normals...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">NormalEstimationOMP</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="p">,</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">nest</span><span class="p">;</span>
  <span class="n">nest</span><span class="p">.</span><span class="n">setRadiusSearch</span> <span class="p">(</span><span class="mf">0.01</span><span class="p">);</span>
  <span class="n">nest</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">nest</span><span class="p">.</span><span class="n">compute</span> <span class="p">(</span><span class="o">*</span><span class="n">scene</span><span class="p">);</span>
  
  <span class="c1">// Estimate features</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Estimating features...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">FeatureEstimationT</span> <span class="n">fest</span><span class="p">;</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setRadiusSearch</span> <span class="p">(</span><span class="mf">0.025</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputNormals</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">compute</span> <span class="p">(</span><span class="o">*</span><span class="n">object_features</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputNormals</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">compute</span> <span class="p">(</span><span class="o">*</span><span class="n">scene_features</span><span class="p">);</span>
  
  <span class="c1">// Perform alignment</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Starting alignment...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">SampleConsensusPrerejective</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="p">,</span><span class="n">PointNT</span><span class="p">,</span><span class="n">FeatureT</span><span class="o">&gt;</span> <span class="n">align</span><span class="p">;</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setInputSource</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setSourceFeatures</span> <span class="p">(</span><span class="n">object_features</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setInputTarget</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setTargetFeatures</span> <span class="p">(</span><span class="n">scene_features</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setMaximumIterations</span> <span class="p">(</span><span class="mi">50000</span><span class="p">);</span> <span class="c1">// Number of RANSAC iterations</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setNumberOfSamples</span> <span class="p">(</span><span class="mi">3</span><span class="p">);</span> <span class="c1">// Number of points to sample for generating/prerejecting a pose</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setCorrespondenceRandomness</span> <span class="p">(</span><span class="mi">5</span><span class="p">);</span> <span class="c1">// Number of nearest features to use</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setSimilarityThreshold</span> <span class="p">(</span><span class="mf">0.9f</span><span class="p">);</span> <span class="c1">// Polygonal edge length similarity threshold</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setMaxCorrespondenceDistance</span> <span class="p">(</span><span class="mf">2.5f</span> <span class="o">*</span> <span class="n">leaf</span><span class="p">);</span> <span class="c1">// Inlier threshold</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setInlierFraction</span> <span class="p">(</span><span class="mf">0.25f</span><span class="p">);</span> <span class="c1">// Required inlier fraction for accepting a pose hypothesis</span>
  <span class="p">{</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">ScopeTime</span> <span class="n">t</span><span class="p">(</span><span class="s">&quot;Alignment&quot;</span><span class="p">);</span>
    <span class="n">align</span><span class="p">.</span><span class="n">align</span> <span class="p">(</span><span class="o">*</span><span class="n">object_aligned</span><span class="p">);</span>
  <span class="p">}</span>
  
  <span class="k">if</span> <span class="p">(</span><span class="n">align</span><span class="p">.</span><span class="n">hasConverged</span> <span class="p">())</span>
  <span class="p">{</span>
    <span class="c1">// Print results</span>
    <span class="n">printf</span> <span class="p">(</span><span class="s">&quot;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="n">Eigen</span><span class="o">::</span><span class="n">Matrix4f</span> <span class="n">transformation</span> <span class="o">=</span> <span class="n">align</span><span class="p">.</span><span class="n">getFinalTransformation</span> <span class="p">();</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;    | %6.3f %6.3f %6.3f | </span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">0</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">1</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">2</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;R = | %6.3f %6.3f %6.3f | </span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">0</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">1</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">2</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;    | %6.3f %6.3f %6.3f | </span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">0</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">1</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">2</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;t = &lt; %0.3f, %0.3f, %0.3f &gt;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">3</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">3</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">3</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;Inliers: %i/%i</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">align</span><span class="p">.</span><span class="n">getInliers</span> <span class="p">().</span><span class="n">size</span> <span class="p">(),</span> <span class="n">object</span><span class="o">-&gt;</span><span class="n">size</span> <span class="p">());</span>
    
    <span class="c1">// Show alignment</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">visualization</span><span class="o">::</span><span class="n">PCLVisualizer</span> <span class="n">visu</span><span class="p">(</span><span class="s">&quot;Alignment&quot;</span><span class="p">);</span>
    <span class="n">visu</span><span class="p">.</span><span class="n">addPointCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">,</span> <span class="n">ColorHandlerT</span> <span class="p">(</span><span class="n">scene</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">,</span> <span class="mf">255.0</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">),</span> <span class="s">&quot;scene&quot;</span><span class="p">);</span>
    <span class="n">visu</span><span class="p">.</span><span class="n">addPointCloud</span> <span class="p">(</span><span class="n">object_aligned</span><span class="p">,</span> <span class="n">ColorHandlerT</span> <span class="p">(</span><span class="n">object_aligned</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">,</span> <span class="mf">255.0</span><span class="p">),</span> <span class="s">&quot;object_aligned&quot;</span><span class="p">);</span>
    <span class="n">visu</span><span class="p">.</span><span class="n">spin</span> <span class="p">();</span>
  <span class="p">}</span>
  <span class="k">else</span>
  <span class="p">{</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_error</span> <span class="p">(</span><span class="s">&quot;Alignment failed!</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="k">return</span> <span class="p">(</span><span class="mi">1</span><span class="p">);</span>
  <span class="p">}</span>
  
  <span class="k">return</span> <span class="p">(</span><span class="mi">0</span><span class="p">);</span>
<span class="p">}</span>
</pre></div>
</td></tr></table></div>
</div>
<div class="section" id="the-explanation">
<h1>The explanation</h1>
<p>We start by defining convenience types in order not to clutter the code.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span><span class="c1">// Types</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">PointNormal</span> <span class="n">PointNT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">PointCloud</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">PointCloudT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">FPFHSignature33</span> <span class="n">FeatureT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">FPFHEstimationOMP</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="p">,</span><span class="n">PointNT</span><span class="p">,</span><span class="n">FeatureT</span><span class="o">&gt;</span> <span class="n">FeatureEstimationT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">PointCloud</span><span class="o">&lt;</span><span class="n">FeatureT</span><span class="o">&gt;</span> <span class="n">FeatureCloudT</span><span class="p">;</span>
<span class="k">typedef</span> <span class="n">pcl</span><span class="o">::</span><span class="n">visualization</span><span class="o">::</span><span class="n">PointCloudColorHandlerCustom</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">ColorHandlerT</span><span class="p">;</span>
</pre></div>
</div>
<p>Then we instantiate the necessary data containers, check the input arguments and load the object and scene point clouds. Although we have defined the basic point type to contain normals, we only have those in advance for the object (which is often the case). We will estimate the normal information for the scene below.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span>  <span class="c1">// Point clouds</span>
  <span class="n">PointCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">object</span> <span class="p">(</span><span class="k">new</span> <span class="n">PointCloudT</span><span class="p">);</span>
  <span class="n">PointCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">object_aligned</span> <span class="p">(</span><span class="k">new</span> <span class="n">PointCloudT</span><span class="p">);</span>
  <span class="n">PointCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">scene</span> <span class="p">(</span><span class="k">new</span> <span class="n">PointCloudT</span><span class="p">);</span>
  <span class="n">FeatureCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">object_features</span> <span class="p">(</span><span class="k">new</span> <span class="n">FeatureCloudT</span><span class="p">);</span>
  <span class="n">FeatureCloudT</span><span class="o">::</span><span class="n">Ptr</span> <span class="n">scene_features</span> <span class="p">(</span><span class="k">new</span> <span class="n">FeatureCloudT</span><span class="p">);</span>
  
  <span class="c1">// Get input object and scene</span>
  <span class="k">if</span> <span class="p">(</span><span class="n">argc</span> <span class="o">!=</span> <span class="mi">3</span><span class="p">)</span>
  <span class="p">{</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_error</span> <span class="p">(</span><span class="s">&quot;Syntax is: %s object.pcd scene.pcd</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">argv</span><span class="p">[</span><span class="mi">0</span><span class="p">]);</span>
    <span class="k">return</span> <span class="p">(</span><span class="mi">1</span><span class="p">);</span>
  <span class="p">}</span>
  
  <span class="c1">// Load object and scene</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Loading point clouds...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="k">if</span> <span class="p">(</span><span class="n">pcl</span><span class="o">::</span><span class="n">io</span><span class="o">::</span><span class="n">loadPCDFile</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="p">(</span><span class="n">argv</span><span class="p">[</span><span class="mi">1</span><span class="p">],</span> <span class="o">*</span><span class="n">object</span><span class="p">)</span> <span class="o">&lt;</span> <span class="mi">0</span> <span class="o">||</span>
      <span class="n">pcl</span><span class="o">::</span><span class="n">io</span><span class="o">::</span><span class="n">loadPCDFile</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="p">(</span><span class="n">argv</span><span class="p">[</span><span class="mi">2</span><span class="p">],</span> <span class="o">*</span><span class="n">scene</span><span class="p">)</span> <span class="o">&lt;</span> <span class="mi">0</span><span class="p">)</span>
  <span class="p">{</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_error</span> <span class="p">(</span><span class="s">&quot;Error loading object/scene file!</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="k">return</span> <span class="p">(</span><span class="mi">1</span><span class="p">);</span>
  <span class="p">}</span>
</pre></div>
</div>
<p>To speed up processing, we use PCL’s <a href="#id1"><span class="problematic" id="id2">:pcl:`VoxelGrid &lt;pcl::VoxelGrid&gt;`</span></a> class to downsample both the object and the scene point clouds to a resolution of 5 mm.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span>  <span class="c1">// Downsample</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Downsampling...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">VoxelGrid</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">grid</span><span class="p">;</span>
  <span class="k">const</span> <span class="kt">float</span> <span class="n">leaf</span> <span class="o">=</span> <span class="mf">0.005f</span><span class="p">;</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">setLeafSize</span> <span class="p">(</span><span class="n">leaf</span><span class="p">,</span> <span class="n">leaf</span><span class="p">,</span> <span class="n">leaf</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">filter</span> <span class="p">(</span><span class="o">*</span><span class="n">object</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">grid</span><span class="p">.</span><span class="n">filter</span> <span class="p">(</span><span class="o">*</span><span class="n">scene</span><span class="p">);</span>
</pre></div>
</div>
<p>The missing surface normals for the scene are now estimated using PCL’s <a href="#id3"><span class="problematic" id="id4">:pcl:`NormalEstimationOMP &lt;pcl::NormalEstimationOMP&gt;`</span></a>. The surface normals are required for computing the features below used for matching.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span>  <span class="c1">// Estimate normals for scene</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Estimating scene normals...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">NormalEstimationOMP</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="p">,</span><span class="n">PointNT</span><span class="o">&gt;</span> <span class="n">nest</span><span class="p">;</span>
  <span class="n">nest</span><span class="p">.</span><span class="n">setRadiusSearch</span> <span class="p">(</span><span class="mf">0.01</span><span class="p">);</span>
  <span class="n">nest</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">nest</span><span class="p">.</span><span class="n">compute</span> <span class="p">(</span><span class="o">*</span><span class="n">scene</span><span class="p">);</span>
</pre></div>
</div>
<p>For each point in the downsampled point clouds, we now use PCL’s <a href="#id5"><span class="problematic" id="id6">:pcl:`FPFHEstimationOMP &lt;pcl::FPFHEstimationOMP&gt;`</span></a> class to compute <em>Fast Point Feature Histogram</em> (FPFH) descriptors used for matching during the alignment process.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span>  <span class="c1">// Estimate features</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Estimating features...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">FeatureEstimationT</span> <span class="n">fest</span><span class="p">;</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setRadiusSearch</span> <span class="p">(</span><span class="mf">0.025</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputNormals</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">compute</span> <span class="p">(</span><span class="o">*</span><span class="n">object_features</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">setInputNormals</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">fest</span><span class="p">.</span><span class="n">compute</span> <span class="p">(</span><span class="o">*</span><span class="n">scene_features</span><span class="p">);</span>
</pre></div>
</div>
<p>We are now ready to setup the alignment process. We use the class <a href="#id7"><span class="problematic" id="id8">:pcl:`SampleConsensusPrerejective &lt;pcl::SampleConsensusPrerejective&gt;`</span></a>, which implements an efficient RANSAC pose estimation loop. This is achieved by early elimination of bad pose hypothesis using the class <a href="#id9"><span class="problematic" id="id10">:pcl:`CorrespondenceRejectorPoly &lt;pcl::registration::CorrespondenceRejectorPoly&gt;`</span></a>.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span>  <span class="c1">// Perform alignment</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_highlight</span> <span class="p">(</span><span class="s">&quot;Starting alignment...</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
  <span class="n">pcl</span><span class="o">::</span><span class="n">SampleConsensusPrerejective</span><span class="o">&lt;</span><span class="n">PointNT</span><span class="p">,</span><span class="n">PointNT</span><span class="p">,</span><span class="n">FeatureT</span><span class="o">&gt;</span> <span class="n">align</span><span class="p">;</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setInputSource</span> <span class="p">(</span><span class="n">object</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setSourceFeatures</span> <span class="p">(</span><span class="n">object_features</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setInputTarget</span> <span class="p">(</span><span class="n">scene</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setTargetFeatures</span> <span class="p">(</span><span class="n">scene_features</span><span class="p">);</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setMaximumIterations</span> <span class="p">(</span><span class="mi">50000</span><span class="p">);</span> <span class="c1">// Number of RANSAC iterations</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setNumberOfSamples</span> <span class="p">(</span><span class="mi">3</span><span class="p">);</span> <span class="c1">// Number of points to sample for generating/prerejecting a pose</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setCorrespondenceRandomness</span> <span class="p">(</span><span class="mi">5</span><span class="p">);</span> <span class="c1">// Number of nearest features to use</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setSimilarityThreshold</span> <span class="p">(</span><span class="mf">0.9f</span><span class="p">);</span> <span class="c1">// Polygonal edge length similarity threshold</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setMaxCorrespondenceDistance</span> <span class="p">(</span><span class="mf">2.5f</span> <span class="o">*</span> <span class="n">leaf</span><span class="p">);</span> <span class="c1">// Inlier threshold</span>
  <span class="n">align</span><span class="p">.</span><span class="n">setInlierFraction</span> <span class="p">(</span><span class="mf">0.25f</span><span class="p">);</span> <span class="c1">// Required inlier fraction for accepting a pose hypothesis</span>
</pre></div>
</div>
<div class="admonition note">
<p class="admonition-title">Note</p>
<p>Apart from the usual input point clouds and features, this class takes some additional runtime parameters which have great influence on the performance of the alignment algorithm. The first two have the same meaning as in the alignment class <a href="#id11"><span class="problematic" id="id12">:pcl:`SampleConsensusInitialAlignment &lt;pcl::SampleConsensusInitialAlignment&gt;`</span></a>:</p>
<ul class="simple">
<li><p>Number of samples - <em>setNumberOfSamples ()</em>: The number of point correspondences to sample between the object and the scene. At minimum, 3 points are required to calculate a pose.</p></li>
<li><p>Correspondence randomness - <em>setCorrespondenceRandomness ()</em>: Instead of matching each object FPFH descriptor to its nearest matching feature in the scene, we can choose between the <em>N</em> best matches at random. This increases the iterations necessary, but also makes the algorithm robust towards outlier matches.</p></li>
<li><p>Polygonal similarity threshold - <em>setSimilarityThreshold ()</em>: The alignment class uses the <a href="#id13"><span class="problematic" id="id14">:pcl:`CorrespondenceRejectorPoly &lt;pcl::registration::CorrespondenceRejectorPoly&gt;`</span></a> class for early elimination of bad poses based on pose-invariant geometric consistencies of the inter-distances between sampled points on the object and the scene. The closer this value is set to 1, the more greedy and thereby fast the algorithm becomes. However, this also increases the risk of eliminating good poses when noise is present.</p></li>
<li><p>Inlier threshold - <em>setMaxCorrespondenceDistance ()</em>: This is the Euclidean distance threshold used for determining whether a transformed object point is correctly aligned to the nearest scene point or not. In this example, we have used a heuristic value of 1.5 times the point cloud resolution.</p></li>
<li><p>Inlier fraction - <em>setInlierFraction ()</em>: In many practical scenarios, large parts of the observed object in the scene are not visible, either due to clutter, occlusions or both. In such cases, we need to allow for pose hypotheses that do not align all object points to the scene. The absolute number of correctly aligned points is determined using the inlier threshold, and if the ratio of this number to the total number of points in the object is higher than the specified inlier fraction, we accept a pose hypothesis as valid.</p></li>
</ul>
</div>
<p>Finally, we are ready to execute the alignment process.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span>  <span class="p">{</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">ScopeTime</span> <span class="n">t</span><span class="p">(</span><span class="s">&quot;Alignment&quot;</span><span class="p">);</span>
    <span class="n">align</span><span class="p">.</span><span class="n">align</span> <span class="p">(</span><span class="o">*</span><span class="n">object_aligned</span><span class="p">);</span>
  <span class="p">}</span>
</pre></div>
</div>
<p>The aligned object is stored in the point cloud <em>object_aligned</em>. If a pose with enough inliers was found (more than 25 % of the total number of object points), the algorithm is said to converge, and we can print and visualize the results.</p>
<div class="highlight-cpp notranslate"><div class="highlight"><pre><span></span>    <span class="c1">// Print results</span>
    <span class="n">printf</span> <span class="p">(</span><span class="s">&quot;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="n">Eigen</span><span class="o">::</span><span class="n">Matrix4f</span> <span class="n">transformation</span> <span class="o">=</span> <span class="n">align</span><span class="p">.</span><span class="n">getFinalTransformation</span> <span class="p">();</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;    | %6.3f %6.3f %6.3f | </span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">0</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">1</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">2</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;R = | %6.3f %6.3f %6.3f | </span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">0</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">1</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">2</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;    | %6.3f %6.3f %6.3f | </span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">0</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">1</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">2</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;t = &lt; %0.3f, %0.3f, %0.3f &gt;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">0</span><span class="p">,</span><span class="mi">3</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">1</span><span class="p">,</span><span class="mi">3</span><span class="p">),</span> <span class="n">transformation</span> <span class="p">(</span><span class="mi">2</span><span class="p">,</span><span class="mi">3</span><span class="p">));</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">);</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">console</span><span class="o">::</span><span class="n">print_info</span> <span class="p">(</span><span class="s">&quot;Inliers: %i/%i</span><span class="se">\n</span><span class="s">&quot;</span><span class="p">,</span> <span class="n">align</span><span class="p">.</span><span class="n">getInliers</span> <span class="p">().</span><span class="n">size</span> <span class="p">(),</span> <span class="n">object</span><span class="o">-&gt;</span><span class="n">size</span> <span class="p">());</span>
    
    <span class="c1">// Show alignment</span>
    <span class="n">pcl</span><span class="o">::</span><span class="n">visualization</span><span class="o">::</span><span class="n">PCLVisualizer</span> <span class="n">visu</span><span class="p">(</span><span class="s">&quot;Alignment&quot;</span><span class="p">);</span>
    <span class="n">visu</span><span class="p">.</span><span class="n">addPointCloud</span> <span class="p">(</span><span class="n">scene</span><span class="p">,</span> <span class="n">ColorHandlerT</span> <span class="p">(</span><span class="n">scene</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">,</span> <span class="mf">255.0</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">),</span> <span class="s">&quot;scene&quot;</span><span class="p">);</span>
    <span class="n">visu</span><span class="p">.</span><span class="n">addPointCloud</span> <span class="p">(</span><span class="n">object_aligned</span><span class="p">,</span> <span class="n">ColorHandlerT</span> <span class="p">(</span><span class="n">object_aligned</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">,</span> <span class="mf">0.0</span><span class="p">,</span> <span class="mf">255.0</span><span class="p">),</span> <span class="s">&quot;object_aligned&quot;</span><span class="p">);</span>
    <span class="n">visu</span><span class="p">.</span><span class="n">spin</span> <span class="p">();</span>
</pre></div>
</div>
</div>
<div class="section" id="compiling-and-running-the-program">
<h1>Compiling and running the program</h1>
<p>Create a <code class="docutils literal notranslate"><span class="pre">CMakeLists.txt</span></code> file with the following content (or download it <a class="reference download internal" download="" href="_downloads/29f1b6a229deb6a93763464ae3044d18/CMakeLists.txt"><code class="xref download docutils literal notranslate"><span class="pre">here</span></code></a>):</p>
<div class="highlight-cmake notranslate"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre> 1
 2
 3
 4
 5
 6
 7
 8
 9
10
11
12</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="nb">cmake_minimum_required</span><span class="p">(</span><span class="s">VERSION</span> <span class="s">2.8</span> <span class="s">FATAL_ERROR</span><span class="p">)</span>

<span class="nb">project</span><span class="p">(</span><span class="s">alignment_prerejective</span><span class="p">)</span>

<span class="nb">find_package</span><span class="p">(</span><span class="s">PCL</span> <span class="s">1.7</span> <span class="s">REQUIRED</span> <span class="s">REQUIRED</span> <span class="s">COMPONENTS</span> <span class="s">io</span> <span class="s">registration</span> <span class="s">segmentation</span> <span class="s">visualization</span><span class="p">)</span>

<span class="nb">include_directories</span><span class="p">(</span><span class="o">${</span><span class="nv">PCL_INCLUDE_DIRS</span><span class="o">}</span><span class="p">)</span>
<span class="nb">link_directories</span><span class="p">(</span><span class="o">${</span><span class="nv">PCL_LIBRARY_DIRS</span><span class="o">}</span><span class="p">)</span>
<span class="nb">add_definitions</span><span class="p">(</span><span class="o">${</span><span class="nv">PCL_DEFINITIONS</span><span class="o">}</span><span class="p">)</span>

<span class="nb">add_executable</span> <span class="p">(</span><span class="s">alignment_prerejective</span> <span class="s">alignment_prerejective.cpp</span><span class="p">)</span>
<span class="nb">target_link_libraries</span> <span class="p">(</span><span class="s">alignment_prerejective</span> <span class="o">${</span><span class="nv">PCL_LIBRARIES</span><span class="o">}</span><span class="p">)</span>
</pre></div>
</td></tr></table></div>
<p>After you have made the executable, you can run it like so:</p>
<div class="highlight-default notranslate"><div class="highlight"><pre><span></span>$ ./alignment_prerejective chef.pcd rs1.pcd
</pre></div>
</div>
<p>After a few seconds, you will see a visualization and a terminal output similar to:</p>
<div class="highlight-default notranslate"><div class="highlight"><pre><span></span><span class="n">Alignment</span> <span class="n">took</span> <span class="mi">352</span><span class="n">ms</span><span class="o">.</span>

    <span class="o">|</span>  <span class="mf">0.040</span> <span class="o">-</span><span class="mf">0.929</span> <span class="o">-</span><span class="mf">0.369</span> <span class="o">|</span>
<span class="n">R</span> <span class="o">=</span> <span class="o">|</span> <span class="o">-</span><span class="mf">0.999</span> <span class="o">-</span><span class="mf">0.035</span> <span class="o">-</span><span class="mf">0.020</span> <span class="o">|</span>
    <span class="o">|</span>  <span class="mf">0.006</span>  <span class="mf">0.369</span> <span class="o">-</span><span class="mf">0.929</span> <span class="o">|</span>

<span class="n">t</span> <span class="o">=</span> <span class="o">&lt;</span> <span class="o">-</span><span class="mf">0.287</span><span class="p">,</span> <span class="mf">0.045</span><span class="p">,</span> <span class="mf">0.126</span> <span class="o">&gt;</span>

<span class="n">Inliers</span><span class="p">:</span> <span class="mi">987</span><span class="o">/</span><span class="mi">3432</span>
</pre></div>
</div>
<p>The visualization window should look something like the below figures. The scene is shown with green color, and the aligned object model is shown with blue color. Note the high number of non-visible object points.</p>
<div class="figure align-center" id="id15">
<a class="reference internal image-reference" href="_images/alignment_prerejective_1.png"><img alt="_images/alignment_prerejective_1.png" src="_images/alignment_prerejective_1.png" style="width: 600.0px; height: 337.5px;" /></a>
<p class="caption"><span class="caption-text"><em>Frontal view</em></span></p>
</div>
<div class="line-block">
<div class="line"><br /></div>
</div>
<div class="figure align-center" id="id16">
<a class="reference internal image-reference" href="_images/alignment_prerejective_2.png"><img alt="_images/alignment_prerejective_2.png" src="_images/alignment_prerejective_2.png" style="width: 600.0px; height: 337.5px;" /></a>
<p class="caption"><span class="caption-text"><em>Side view</em></span></p>
</div>
</div>


          </div>
      </div>
      <div class="clearer"></div>
    </div>
</div> <!-- #page-content -->

<?php
$chunkOutput = $modx->getChunk("site-footer");
echo $chunkOutput;
?>

  </body>
</html>