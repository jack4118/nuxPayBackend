<?php
/**
 * This file is automatically @generated by {@link BuildMetadataPHPFromXml}.
 * Please don't modify it directly.
 */


return array (
  'generalDesc' => 
  array (
    'NationalNumberPattern' => '
          1\\d{7,12}|
          [2-9]\\d{9,10}
        ',
    'PossibleNumberPattern' => '\\d{6,13}',
  ),
  'fixedLine' => 
  array (
    'NationalNumberPattern' => '
          (?:
            11|
            2[02]|
            33|
            4[04]|
            79
          )[2-7]\\d{7}|
          80[2-467]\\d{7}|
          (?:
            1(?:
              2[0-249]|
              3[0-25]|
              4[145]|
              [59][14]|
              6[014]|
              7[1257]|
              8[01346]
            )|
            2(?:
              1[257]|
              3[013]|
              4[01]|
              5[0137]|
              6[0158]|
              78|
              8[1568]|
              9[14]
            )|
            3(?:
              26|
              4[1-3]|
              5[34]|
              6[01489]|
              7[02-46]|
              8[159]
            )|
            4(?:
              1[36]|
              2[1-47]|
              3[15]|
              5[12]|
              6[0-26-9]|
              7[0-24-9]|
              8[013-57]|
              9[014-7]
            )|
            5(?:
              1[025]|
              [36][25]|
              22|
              4[28]|
              5[12]|
              [78]1|
              9[15]
            )|
            6(?:
              12|
              [2345]1|
              57|
              6[13]|
              7[14]|
              80
            )|
            7(?:
              12|
              2[14]|
              3[134]|
              4[47]|
              5[15]|
              [67]1|
              88
            )|
            8(?:
              16|
              2[014]|
              3[126]|
              6[136]|
              7[078]|
              8[34]|
              91
            )
          )[2-7]\\d{6}|
          (?:
            (?:
              1(?:
                2[35-8]|
                3[346-9]|
                4[236-9]|
                [59][0235-9]|
                6[235-9]|
                7[34689]|
                8[257-9]
              )|
              2(?:
                1[134689]|
                3[24-8]|
                4[2-8]|
                5[25689]|
                6[2-4679]|
                7[13-79]|
                8[2-479]|
                9[235-9]
              )|
              3(?:
                01|
                1[79]|
                2[1-5]|
                4[25-8]|
                5[125689]|
                6[235-7]|
                7[157-9]|
                8[2-467]
              )|
              4(?:
                1[14578]|
                2[5689]|
                3[2-467]|
                5[4-7]|
                6[35]|
                73|
                8[2689]|
                9[2389]
              )|
              5(?:
                [16][146-9]|
                2[14-8]|
                3[1346]|
                4[14-69]|
                5[46]|
                7[2-4]|
                8[2-8]|
                9[246]
              )|
              6(?:
                1[1358]|
                2[2457]|
                3[2-4]|
                4[235-7]|
                [57][2-689]|
                6[24-578]|
                8[1-6]
              )|
              8(?:
                1[1357-9]|
                2[235-8]|
                3[03-57-9]|
                4[0-24-9]|
                5\\d|
                6[2457-9]|
                7[1-6]|
                8[1256]|
                9[2-4]
              )
            )\\d|
            7(?:
              (?:
                1[013-9]|
                2[0235-9]|
                3[2679]|
                4[1-35689]|
                5[2-46-9]|
                [67][02-9]|
                9\\d
              )\\d|
              8(?:
                2[0-6]|
                [013-8]\\d
              )
            )
          )[2-7]\\d{5}
        ',
    'PossibleNumberPattern' => '\\d{6,10}',
    'ExampleNumber' => '1123456789',
  ),
  'mobile' => 
  array (
    'NationalNumberPattern' => '
          (?:
            7(?:
              0\\d{3}|
              2(?:
                [0235679]\\d{2}|
                [14][017-9]\\d|
                8(?:
                  [0-59]\\d|
                  6[089]
                )|
                9[389]\\d
              )|
              3(?:
                [05-8]\\d{2}|
                1(?:
                  [089]\\d|
                  7[5-8]
                )|
                2(?:
                  [5-8]\\d|
                  [01][089]
                )|
                3[17-9]\\d|
                4(?:
                  [07-9]\\d|
                  11
                )|
                9(?:
                  [01689]\\d|
                  59
                )
              )|
              4(?:
                0[1-9]\\d|
                1(?:
                  [015-9]\\d|
                  4[08]
                )|
                2(?:
                  [1-7][089]|
                  [89]\\d
                )|
                3(?:
                  [0-8][089]|
                  9\\d
                )|
                4(?:
                  [089]\\d|
                  11|
                  7[02-8]
                )|
                5(?:
                  0[089]|
                  99
                )|
                7(?:
                  0[3-9]|
                  11|
                  7[02-8]|
                  [89]\\d
                )|
                8(?:
                  [0-24-7][089]|
                  [389]\\d
                )|
                9(?:
                  [0-6][089]|
                  7[08]|
                  [89]\\d
                )
              )|
              5(?:
                [034678]\\d|
                2[03-9]|
                5[017-9]|
                9[7-9]
              )\\d|
              6(?:
                0[0-47]|
                1[0-257-9]|
                2[0-4]|
                3[19]|
                5[4589]|
                [6-9]\\d
              )\\d|
              7(?:
                0[2-9]|
                [1-79]\\d|
                8[1-9]
              )\\d|
              8(?:
                [0-79]\\d{2}|
                880
              )|
              99[4-9]\\d
            )|
            8(?:
              0(?:
                [01589]\\d|
                6[67]
              )|
              1(?:
                [02-57-9]\\d|
                1[0135-9]
              )|
              2(?:
                [236-9]\\d|
                5[1-9]
              )|
              3(?:
                [0357-9]\\d|
                4[1-9]
              )|
              [45]\\d{2}|
              6[02457-9]\\d|
              7(?:
                07|
                [1-69]\\d
              )|
              8(?:
                [0-26-9]\\d|
                44|
                5[2-9]
              )|
              9(?:
                [035-9]\\d|
                2[2-9]|
                4[0-8]
              )
            )\\d|
            9\\d{4}
          )\\d{5}
        ',
    'PossibleNumberPattern' => '\\d{10}',
    'ExampleNumber' => '9987654321',
  ),
  'tollFree' => 
  array (
    'NationalNumberPattern' => '
          1(?:
            600\\d{6}|
            80(?:
              0\\d{4,9}|
              3\\d{9}
            )
          )
        ',
    'PossibleNumberPattern' => '\\d{8,13}',
    'ExampleNumber' => '1800123456',
  ),
  'premiumRate' => 
  array (
    'NationalNumberPattern' => '186[12]\\d{9}',
    'PossibleNumberPattern' => '\\d{13}',
    'ExampleNumber' => '1861123456789',
  ),
  'sharedCost' => 
  array (
    'NationalNumberPattern' => '1860\\d{7}',
    'PossibleNumberPattern' => '\\d{11}',
    'ExampleNumber' => '18603451234',
  ),
  'personalNumber' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'voip' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'pager' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'uan' => 
  array (
    'NationalNumberPattern' => '140\\d{7}',
    'PossibleNumberPattern' => '\\d{10}',
    'ExampleNumber' => '1409305260',
  ),
  'emergency' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'voicemail' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'shortCode' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'standardRate' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'carrierSpecific' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'noInternationalDialling' => 
  array (
    'NationalNumberPattern' => '
          1(?:
            600\\d{6}|
            8(?:
              0(?:
                0\\d{4,9}|
                3\\d{9}
              )|
              6(?:
                0\\d{7}|
                [12]\\d{9}
              )
            )
          )
        ',
    'PossibleNumberPattern' => '\\d{8,13}',
    'ExampleNumber' => '1800123456',
  ),
  'id' => 'IN',
  'countryCode' => 91,
  'internationalPrefix' => '00',
  'nationalPrefix' => '0',
  'nationalPrefixForParsing' => '0',
  'sameMobileAndFixedLinePattern' => false,
  'numberFormat' => 
  array (
    0 => 
    array (
      'pattern' => '(\\d{5})(\\d{5})',
      'format' => '$1 $2',
      'leadingDigitsPatterns' => 
      array (
        0 => '
            7(?:
              [023578]|
              4[0-57-9]|
              6[0-35-9]|
              99
            )|
            8(?:
              0[015689]|
              1[0-57-9]|
              2[2356-9]|
              3[0-57-9]|
              [45]|
              6[02457-9]|
              7[01-69]|
              8[0-24-9]|
              9[02-9]
            )|
            9
          ',
        1 => '
            7(?:
              [08]|
              2(?:
                [0235679]|
                [14][017-9]|
                8[0-569]|
                9[389]
              )|
              3(?:
                [05-8]|
                1[07-9]|
                2[015-8]|
                3[17-9]|
                4[017-9]|
                9[015689]
              )|
              4(?:
                [02][1-9]|
                1[014-9]|
                3\\d|
                [47][017-9]|
                5[09]|
                [89]
              )|
              5(?:
                [034678]|
                2[03-9]|
                5[017-9]|
                9[7-9]
              )|
              6(?:
                0[0-47]|
                1[0-257-9]|
                2[0-4]|
                3[19]|
                5[4589]|
                [6-9]
              )|
              7(?:
                0[2-9]|
                [1-79]|
                8[1-9]
              )|
              99[4-9]
            )|
            8(?:
              0(?:
                [01589]|
                6[67]
              )|
              1(?:
                [02-57-9]|
                1[0135-9]
              )|
              2(?:
                [236-9]|
                5[1-9]
              )|
              3(?:
                [0357-9]|
                4[1-9]
              )|
              [45]|
              6[02457-9]|
              7(?:
                07|
                [1-69]
              )|
              8(?:
                [0-26-9]|
                44|
                5[2-9]
              )|
              9(?:
                [035-9]|
                2[2-9]|
                4[0-8]
              )
            )|
            9
          ',
        2 => '
            7(?:
              0|
              2(?:
                [0235679]|
                [14][017-9]|
                8[0-569]|
                9[389]
              )|
              3(?:
                [05-8]|
                1(?:
                  [089]|
                  7[5-9]
                )|
                2(?:
                  [5-8]|
                  [01][089]
                )|
                3[17-9]|
                4(?:
                  [07-9]|
                  11
                )|
                9(?:
                  [01689]|
                  59
                )
              )|
              4(?:
                0[1-9]|
                1(?:
                  [015-9]|
                  4[08]
                )|
                2(?:
                  [1-7][089]|
                  [89]
                )|
                3(?:
                  [0-8][089]|
                  9
                )|
                4(?:
                  [089]|
                  11|
                  7[02-8]
                )|
                5(?:
                  0[089]|
                  99
                )|
                7(?:
                  0[3-9]|
                  11|
                  7[02-8]|
                  [89]
                )|
                8(?:
                  [0-24-7][089]|
                  [389]
                )|
                9(?:
                  [0-6][089]|
                  7[08]|
                  [89]
                )
              )|
              5(?:
                [034678]|
                2[03-9]|
                5[017-9]|
                9[7-9]
              )|
              6(?:
                0[0-47]|
                1[0-257-9]|
                2[0-4]|
                3[19]|
                5[4589]|
                [6-9]
              )|
              7(?:
                0[2-9]|
                [1-79]|
                8[1-9]
              )|
              8(?:
                [0-79]|
                880
              )|
              99[4-9]
            )|
            8(?:
              0(?:
                [01589]|
                6[67]
              )|
              1(?:
                [02-57-9]|
                1[0135-9]
              )|
              2(?:
                [236-9]|
                5[1-9]
              )|
              3(?:
                [0357-9]|
                4[1-9]
              )|
              [45]|
              6[02457-9]|
              7(?:
                07|
                [1-69]
              )|
              8(?:
                [0-26-9]|
                44|
                5[2-9]
              )|
              9(?:
                [035-9]|
                2[2-9]|
                4[0-8]
              )
            )|
            9
          ',
      ),
      'nationalPrefixFormattingRule' => '0$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    1 => 
    array (
      'pattern' => '(\\d{2})(\\d{4})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '
            11|
            2[02]|
            33|
            4[04]|
            79|
            80[2-46]
          ',
      ),
      'nationalPrefixFormattingRule' => '0$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    2 => 
    array (
      'pattern' => '(\\d{3})(\\d{3})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '
            1(?:
              2[0-249]|
              3[0-25]|
              4[145]|
              [569][14]|
              7[1257]|
              8[1346]|
              [68][1-9]
            )|
            2(?:
              1[257]|
              3[013]|
              4[01]|
              5[0137]|
              6[0158]|
              78|
              8[1568]|
              9[14]
            )|
            3(?:
              26|
              4[1-3]|
              5[34]|
              6[01489]|
              7[02-46]|
              8[159]
            )|
            4(?:
              1[36]|
              2[1-47]|
              3[15]|
              5[12]|
              6[0-26-9]|
              7[0-24-9]|
              8[013-57]|
              9[014-7]
            )|
            5(?:
              1[025]|
              [36][25]|
              22|
              4[28]|
              5[12]|
              [78]1|
              9[15]
            )|
            6(?:
              12|
              [2345]1|
              57|
              6[13]|
              7[14]|
              80
            )
          ',
      ),
      'nationalPrefixFormattingRule' => '0$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    3 => 
    array (
      'pattern' => '(\\d{3})(\\d{3})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '
            7(?:
              12|
              2[14]|
              3[134]|
              4[47]|
              5[15]|
              [67]1|
              88
            )
          ',
        1 => '
            7(?:
              12|
              2[14]|
              3[134]|
              4[47]|
              5(?:
                1|
                5[2-6]
              )|
              [67]1|
              88
            )
          ',
      ),
      'nationalPrefixFormattingRule' => '0$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    4 => 
    array (
      'pattern' => '(\\d{3})(\\d{3})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '
            8(?:
              16|
              2[014]|
              3[126]|
              6[136]|
              7[078]|
              8[34]|
              91
            )
          ',
      ),
      'nationalPrefixFormattingRule' => '0$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    5 => 
    array (
      'pattern' => '(\\d{4})(\\d{3})(\\d{3})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '
            1(?:
              [23579]|
              [468][1-9]
            )|
            [2-8]
          ',
      ),
      'nationalPrefixFormattingRule' => '0$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    6 => 
    array (
      'pattern' => '(1600)(\\d{2})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '160',
        1 => '1600',
      ),
      'nationalPrefixFormattingRule' => '$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    7 => 
    array (
      'pattern' => '(1800)(\\d{4,5})',
      'format' => '$1 $2',
      'leadingDigitsPatterns' => 
      array (
        0 => '180',
        1 => '1800',
      ),
      'nationalPrefixFormattingRule' => '$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    8 => 
    array (
      'pattern' => '(18[06]0)(\\d{2,4})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '18[06]',
        1 => '18[06]0',
      ),
      'nationalPrefixFormattingRule' => '$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    9 => 
    array (
      'pattern' => '(140)(\\d{3})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '140',
      ),
      'nationalPrefixFormattingRule' => '$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    10 => 
    array (
      'pattern' => '(\\d{4})(\\d{3})(\\d{3})(\\d{3})',
      'format' => '$1 $2 $3 $4',
      'leadingDigitsPatterns' => 
      array (
        0 => '18[06]',
        1 => '
            18(?:
              0[03]|
              6[12]
            )
          ',
      ),
      'nationalPrefixFormattingRule' => '$1',
      'domesticCarrierCodeFormattingRule' => '',
    ),
  ),
  'intlNumberFormat' => 
  array (
  ),
  'mainCountryForCode' => false,
  'leadingZeroPossible' => false,
  'mobileNumberPortableRegion' => true,
);
