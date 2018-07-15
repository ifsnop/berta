<?php
  // ------------------------------------------------------------------
  // ------------------------------------------------------------------
  // CONREC is a straightforward method of contouring some surface represented 
  // as a regular triangular mesh.
  //
  // Ported from C / Fortran code by Paul Borke. 
  // http://paulbourke.net/papers/conrec/ 
  //
  // PHP implementation by Ashley Norris.
  // http://norris.org.au/
  //
  // Contouring aids in visualizing three dimensional surfaces on a two dimensional 
  // medium (on paper or in this case a computer graphics screen). Two most common 
  // applications are displaying topological features of an area on a map or the air 
  // pressure on a weather map. In all cases some parameter is plotted as a function 
  // of two variables, the longitude and latitude or x and y axis. One problem with 
  // computer contouring is the process is usually CPU intensive and the algorithms 
  // often use advanced mathematical techniques making them susceptible to error.
  //
  // ------------------------------------------------------------------
  // ------------------------------------------------------------------
  //
  // This PHP implementation accepts a 2D scalar field (a matrix) and returns
  // a set of line segments that would typically then be rendered in some way by the user.
  //
  // The form of the returned data has been made as simple as possible, and could
  // be easily edited (to minimise memory use) by anyone if they so wish.
  //
  // The returned data is an array of this structure:
  // [top] (array of contours)                    // An array containing all of the contours created by this algorithm
  //  -> [contour] (mixed array)
  //        -> 'value'  (double)                    // The scalar value (height) of the contour
  //        -> ['segments'] (array of segments)     // An array containing all the segments (not necessarily continuous) that make up the contour
  //              -> 'x1' (double)
  //              -> 'y1' (double)
  //              -> 'x2' (double)
  //              -> 'y2' (double)
  //
  //  Best thing to do is to use the function and then do a "print_r();" on its returned value to explore the structure returned.
  //
  // ------------------------------------------------------------------
  // ------------------------------------------------------------------
  //
  // This library file produces no output, and contains a definition for one useful function: contour();
  //
  // There are some other functions defined at the end of the file for internal use, which would be useless for external calls.
  //
  // ------------------------------------------------------------------
  // ------------------------------------------------------------------  
  // Function contour($d, $x, $y, $z, $SKIP_QC = false);
  //
  // === Input Parameters ===
  //
  // $d => 2D matrix of values, typically of type double
  // $x => 1D array of the x-coordinate for each column
  // $y => 1D array of the y-coordinate for each row
  // $z => 1D array of the Z value to use for each contour **OR** an integer number of contours to automatically create
  // $SKIP_QC => boolean, set to true to skip the Quality Control stage (speed benefit for large datasets)
  //
  // Some notes:
  //  - $d is a 2D jagged array, with X on the first dim and Y on the second.
  //    I say jagged as PHP does not support a true native multidimensional array
  //    type in the same way as other languages do. Thus, (C, Fortran, etc...) d[i,j] === (PHP) $d[i][j]
  //  - $x must be the same length as the width (first dim) of $d
  //  - $y must be the same length as the height (second dim) of $d
  //  - The $x and $y arrays are not specifically "needed" to create contours, but are 
  //    used to return contour line segments in that coordinate system, rather than a unit system of col and row number.
  //  - $z takes advantage of PHP's flexible typing (yes, it really is good for some things!)
  //    Either, pass in an array of values and the number of contours returned will equal the length of $z
  //    Or, pass in an integer, and that many contours will be returned.
  // ------------------------------------------------------------------
  // ------------------------------------------------------------------  
   
  function CONREC_contour($d, $x, $y, $z, $SKIP_QC = false)
  {
    // ==================================================
    // QC the inputs (turn off for large datasets)
    // If wanting to improve performance, but maintain QC, then feel free to weave the QC checks into the actual algorithm code below
    // ==================================================
    if (!$SKIP_QC) {
      // Basic checks first
      if (!is_array($d)) { die('Function contour(); failed QC. Parameter $d is not an array.'); }
      if (!is_array($x)) { die('Function contour(); failed QC. Parameter $x is not an array.'); }
      if (!is_array($y)) { die('Function contour(); failed QC. Parameter $y is not an array.'); }

      // NOTE: the count() function in PHP is NOT recursive by default
      if (count($d) <= 1) { die('Function contour(); failed QC. Dimension 1 of $d has length smaller than or equal to 1.'); }
      if (count($x) <= 1) { die('Function contour(); failed QC. Array $x has length smaller than or equal to 1.'); }
      if (count($y) <= 1) { die('Function contour(); failed QC. Array $y has length smaller than or equal to 1.'); }

      // Check first dim of $d against $x
      if (count($d) != count($x)) { die('Function contour(); failed QC. Dimension 1 of $d ['.count($d).'] does not match length of $x ['.count($x).'].'); }

      // Loop first dim of $d and check second dim for all rows (as a side-effect this ensures $d is regular)
      foreach($d as $index=>$row) {
        if (!is_int($index)) { die('Function contour(); failed QC. Row "'.$index.'" of $d must have an integer index.'); }
        if (count($row) != count($y)) { die('Function contour(); failed QC. Dimension 2 of row "'.$index.'" of $d does not match length of $y.'); }
        // Check indexes of row
        foreach(array_keys($row) as $col_index) {
          if (!is_int($col_index)) { die('Function contour(); failed QC. On row "'.$index.'", col "'.$col_index.'" of $d must have an integer index.'); } 
        }
      }

      // $z is either an array, or an integer
      if (!is_array($z) AND !is_int($z)) { die('Function contour(); failed QC. Parameter $z must be either an array or an integer.'); }
    }
    // ==================================================


    // ==================================================
    // While PHP does not require us to declare our variables, we do so here anyway, as per the original C code

    $x1 = 0.0;
    $x2 = 0.0;
    $y1 = 0.0;
    $y2 = 0.0;

    // ================================================== 
    // For these arrays we can not specify a fixed length, we just init them with zeros and start using them
    // ================================================== 
    $h = array(0,0,0,0,0);  // Length 5
    $sh = array(0,0,0,0,0); // Length 5
    $xh = array(0,0,0,0,0); // Length 5
    $yh = array(0,0,0,0,0); // Length 5

    // ==================================================
    // When getting the range of the X and Y values we can use the first row of the matrix thanks to the QC step above
    // ==================================================

    // NOTE: the count() function in PHP is NOT recursive by default
    $ilb = 0;         // Can assume this thanks to QC step
    $iub = count($d) - 1; // Counts first dim
    $jlb = 0;         // Can assume this thanks to QC step
    $jub = count($d[0]) - 1;  // Counts second dim of first row, which we can do thanks to QC step

    if (is_array($z)) {
      $nc = count($z);
    }
    else if (is_int($z)) {
      $nc = $z;
    }   
    else {
      // Sensible hard-coded default value, in case QC was skipped
      $nc = 10;
    }


    // ================================================== 
    // Automatic contours (in case only the number has been defined)
    // ================================================== 
    if (!is_array($z)) {
      $z = CONREC_auto_z($nc,$d);
    }

    // ================================================== 
    // Sort $z
    sort($z);

    // ================================================== 
    // The indexing of im and jm should be noted as it has to start from zero
    // unlike the fortran counter part
    $im = array(0, 1, 1, 0 );
    $jm = array( 0, 0, 1, 1 );

    // ================================================== 
    // Note that castab is arranged differently from the FORTRAN code because
    // Fortran and C/C++ arrays are transposed of each other, in this case
    // it is more tricky as castab is in 3 dimensions
    $castab = array(
      array( array( 0, 0, 8 ), array( 0, 2, 5 ), array( 7, 6, 9 ) ),
      array( array( 0, 3, 4 ), array( 1, 3, 1 ), array( 4, 3, 0 ) ),
      array( array( 9, 6, 7 ), array( 5, 2, 0 ), array( 8, 0, 0 ) )
    );

    // ================================================== 
    // We are now ready to perform the contouring, init the return array
    $return_array = array();
    foreach($z as $index=>$value) {
      $return_array[$index] = array('value'=>$value,'segments'=>array());
    }

    // ================================================== 
    // Primary loop: i (row, or $d first dimension) loop begins here
    for ($i = $ilb; $i <= $iub - 1; $i++)
      {
          // ================================================== 
          // Primary loop: j (col, or $d second dimension) loop begins here
          for ($j = $jub - 1; $j >= $jlb; $j--)
          {
              // ================================================== 
              // Find the max and min value of the corners
              $corners = array($d[$i][$j], $d[$i][$j + 1], $d[$i + 1][$j], $d[$i + 1][$j + 1]);
              $dmin = min($corners);
              $dmax = max($corners);
              // Perform elimination of trivial cases
              if ($dmax >= $z[0] && $dmin <= $z[$nc - 1])
              {
                  // Start k (contour level) loop
                  for ($k = 0; $k < $nc; $k++)
                  {
                      // Test against this contour level
                      if ($z[$k] >= $dmin && $z[$k] <= $dmax)
                      {
                          // Loop over $m (corners)
                          for ($m = 4; $m >= 0; $m--)
                          {
                              if ($m > 0)
                              {
                                  // The indexing of im and jm should be noted as it has to
                                  // start from zero
                                  $h[$m] = $d[$i + $im[$m - 1]][ $j + $jm[$m - 1]] - $z[$k];
                                  $xh[$m] = $x[$i + $im[$m - 1]];
                                  $yh[$m] = $y[$j + $jm[$m - 1]];
                              }
                              else
                              {
                                  // Compute average
                                  $h[0] = 0.25 * ($h[1] + $h[2] + $h[3] + $h[4]);
                                  $xh[0] = 0.5 * ($x[$i] + $x[$i + 1]);
                                  $yh[0] = 0.5 * ($y[$j] + $y[$j + 1]);
                              }

                              if ($h[$m] > 0.0)
                              {
                                  $sh[$m] = 1;
                              }
                              else if ($h[$m] < 0.0)
                              {
                                  $sh[$m] = -1;
                              }
                              else
                              {
                                  $sh[$m] = 0;
                              }
                          } // end $m loop

                          // Note: at this stage the relative heights of the corners and the
                          // centre are in the h array, and the corresponding coordinates are
                          // in the xh and yh arrays. The centre of the box is indexed by 0
                          // and the 4 corners by 1 to 4 as shown below.
                          // Each triangle is then indexed by the parameter m, and the 3
                          // vertices of each triangle are indexed by parameters m1,m2,and
                          // m3.
                          // It is assumed that the centre of the box is always vertex 2
                          // though this is important only when all 3 vertices lie exactly on
                          // the same contour level, in which case only the side of the box
                          // is drawn.
                          //
                          // vertex 4
                          // +-------------------+ vertex 3
                          // | \               / |
                          // |   \    m-3    /   |
                          // |     \       /     |
                          // |       \   /       |
                          // |  m=2    X   m=2   |       the centre is vertex 0
                          // |       /   \       |
                          // |     /       \     |
                          // |   /    m=1    \   |
                          // | /               \ |
                          // +-------------------+ vertex 2
                          // vertex 1
                          //
                          // Scan each triangle in the box
                          for ($m = 1; $m <= 4; $m++)
                          {
                              $m1 = $m;
                              $m2 = 0;
                              $m3 = 0;
                              if ($m != 4)
                              {
                                  $m3 = $m + 1;
                              }
                              else
                              {
                                  $m3 = 1;
                              }


                              $caseValue = $castab[$sh[$m1] + 1][$sh[$m2] + 1][$sh[$m3] + 1];
                              if ($caseValue != 0)
                              {
                                  switch ($caseValue)
                                  {
                                      case 1: // Line between vertices 1 and 2
                                          $x1 = $xh[$m1];
                                          $y1 = $yh[$m1];
                                          $x2 = $xh[$m2];
                                          $y2 = $yh[$m2];
                                          break;
                                      case 2: // Line between vertices 2 and 3
                                          $x1 = $xh[$m2];
                                          $y1 = $yh[$m2];
                                          $x2 = $xh[$m3];
                                          $y2 = $yh[$m3];
                                          break;
                                      case 3: // Line between vertices 3 and 1
                                          $x1 = $xh[$m3];
                                          $y1 = $yh[$m3];
                                          $x2 = $xh[$m1];
                                          $y2 = $yh[$m1];
                                          break;
                                      case 4: // Line between vertex 1 and side 2-3
                                          $x1 = $xh[$m1];
                                          $y1 = $yh[$m1];
                                          $x2 = CONREC_sect($m2, $m3, $h, $xh);
                                          $y2 = CONREC_sect($m2, $m3);
                                          break;
                                      case 5: // Line between vertex 2 and side 3-1
                                          $x1 = $xh[$m2];
                                          $y1 = $yh[$m2];
                                          $x2 = CONREC_sect($m3, $m1, $h, $xh);
                                          $y2 = CONREC_sect($m3, $m1, $h, $yh);
                                          break;
                                      case 6: // Line between vertex 3 and side 1-2
                                          $x1 = $xh[$m3];
                                          $y1 = $yh[$m3];
                                          $x2 = CONREC_sect($m1, $m2, $h, $xh);
                                          $y2 = CONREC_sect($m1, $m2, $h, $yh);
                                          break;
                                      case 7: // Line between sides 1-2 and 2-3
                                          $x1 = CONREC_sect($m1, $m2, $h, $xh);
                                          $y1 = CONREC_sect($m1, $m2, $h, $yh);
                                          $x2 = CONREC_sect($m2, $m3, $h, $xh);
                                          $y2 = CONREC_sect($m2, $m3, $h, $yh);
                                          break;
                                      case 8: // Line between sides 2-3 and 3-1
                                          $x1 = CONREC_sect($m2, $m3, $h, $xh);
                                          $y1 = CONREC_sect($m2, $m3, $h, $yh);
                                          $x2 = CONREC_sect($m3, $m1, $h, $xh);
                                          $y2 = CONREC_sect($m3, $m1, $h, $yh);
                                          break;
                                      case 9: // Line between sides 3-1 and 1-2
                                          $x1 = CONREC_sect($m3, $m1, $h, $xh);
                                          $y1 = CONREC_sect($m3, $m1, $h, $yh);
                                          $x2 = CONREC_sect($m1, $m2, $h, $xh);
                                          $y2 = CONREC_sect($m1, $m2, $h, $yh);
                                          break;
                                      default:
                                          break;
                                  }

                                  // ================================================== 
                                  // OUTPUT to return array structure
                                  // ================================================== 
                                  $return_array[$k]['segments'][] = array('x1'=>$x1,'y1'=>$y1,'x2'=>$x2,'y2'=>$y2);

                                  // NOTE: If wishing to improve performance, or reduce memory use, just put your
                                  // rendering routine right here.
                                  // ================================================== 
                              } // end if: caseValue != 0
                          } // end for: m loop
                      }
                  } // end k loop
              } // end if: trivial case elimination (outside contouring range)
          } // end for: primary j loop
      } // end for: primary i loop

      // Return the computed contours
      return $return_array;

  } // end function: conrec();


  // ----------------------------------------------------------------
  // Internal functions
  // ----------------------------------------------------------------

  // As we don't have access to the $xh and $yh parameters we pass them into this single function to perform the segment computation
  function CONREC_sect($p1_index, $p2_index, $h, $dimh) {
    return ($h[$p2_index] * $dimh[$p1_index] - $h[$p1_index] * $dimh[$p2_index]) / ($h[$p2_index] - $h[$p1_index]);
  }

  function CONREC_auto_z($nc, $array) {
      // ================================================== 
      // NOTE: Simply dividing the range by the number of desired contours doesn't 
      // actually work very well, as the max and min values do not produce
      // any contours at all. To correct for this we add 2 to the desired number of
      // contours, divide the range by that number and then use that step to return the
      // actual contour values to use. 
      // ================================================== 
      $z = array();
      $d_min = CONREC_arraymin($array);
      $d_max = CONREC_arraymax($array);
      $d_range = $d_max - $d_min;
      $contour_step = $d_range / ($nc + 2 - 1); // See above for an explanation of this algebra :)
      for($tmp_i = 1;$tmp_i <= $nc;$tmp_i++) {
        $z[] = $d_min + ($contour_step * $tmp_i);
      }
      // $z should now contain $nc elements
      return $z;
  }

  function CONREC_arraymax($array){
    if (is_array($array)){
        foreach($array as $key => $value) {
            $array[$key] = CONREC_arraymax($value);
        }
        return max($array);
    } else{
        return $array;
    }
  }

  function CONREC_arraymin($array){
    if (is_array($array)) {
        foreach($array as $key => $value) {
            $array[$key] = CONREC_arraymin($value);
        }
        return min($array);
    } else{
        return $array;
    }
  }


  // ----------------------------------------------------------------
  // End of file.
  // ----------------------------------------------------------------
