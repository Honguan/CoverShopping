-- shopping.sql 資料庫結構範例
CREATE DATABASE IF NOT EXISTS shopping DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shopping;

-- 會員資料表
CREATE TABLE IF NOT EXISTS register (
  account VARCHAR(32) PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  password VARCHAR(128) NOT NULL,
  mail VARCHAR(128) NOT NULL,
  Birthday DATE,
  switch VARCHAR(16) DEFAULT 'enable',
  checkcode VARCHAR(8),
  login TINYINT(1) DEFAULT 1
);

-- 商品資料表
CREATE TABLE IF NOT EXISTS commodity (
  id VARCHAR(16) PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  content TEXT,
  price INT NOT NULL,
  inventory INT DEFAULT 0,
  account VARCHAR(32),
  image VARCHAR(128)
);

-- 訂單資料表
CREATE TABLE IF NOT EXISTS orders (
  no VARCHAR(16) PRIMARY KEY,
  id VARCHAR(16),
  name VARCHAR(64),
  quantity INT,
  time DATETIME,
  payment TINYINT(1) DEFAULT 0,
  Shipping TINYINT(1) DEFAULT 0,
  freight INT DEFAULT 0
);

-- 留言資料表
CREATE TABLE IF NOT EXISTS information (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64),
  email VARCHAR(128),
  message TEXT,
  time DATETIME,
  ip VARCHAR(32)
);
