--
-- 数据库: `www_curlmulti`
--

-- --------------------------------------------------------

--
-- 表的结构 `content`
--

CREATE TABLE IF NOT EXISTS `content` (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `meta_title` varchar(500) CHARACTER SET latin1 NOT NULL,
  `meta_keywords` text CHARACTER SET latin1 NOT NULL,
  `meta_description` text CHARACTER SET latin1 NOT NULL,
  `product_name` varchar(500) CHARACTER SET latin1 NOT NULL,
  `product_image` varchar(500) CHARACTER SET latin1 NOT NULL,
  `product_price` varchar(500) CHARACTER SET latin1 NOT NULL,
  `product_description` text CHARACTER SET latin1 NOT NULL,
  `product_url` varchar(500) CHARACTER SET latin1 NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
