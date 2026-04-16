# Delivery SaaS 项目总说明（给 Codex）

## 1. 项目目标

开发一个 **可独立运营、支持多商家、多来源订单、API 接入的同城配送履约 SaaS 平台**。

该平台不是某个单一商城的配送模块，而是一个独立的第三方配送履约基础设施平台。平台可接收：

- 团购发布平台订单
- 商家后台订单
- 第三方系统 API 推单
- 平台内部手工创建订单
- 个人跑腿 / 即时配送订单

平台必须支持：

- 多租户 SaaS
- API-first / Headless
- Web + Mobile 自适应 UI
- 商家端、调度端、司机端、平台后台
- 配送状态流转
- Webhook 状态回传
- COD 代收款
- PostgreSQL
- PHP 8

---

## 2. 技术栈要求

### 后端
- PHP 8.x
- PostgreSQL
- API-first 架构
- RESTful JSON API
- 单体应用优先，但架构上保留未来拆分能力
- 严格分层：Controller / Service / Domain / Repository / Middleware / Policy

### 前端
- 电脑与手机自适应
- 手机版要接近 App 风格
- 浅色调、现代、简洁、专业
- 后台和移动端保持统一设计语言
- 优先保证可维护性

### 数据库
- PostgreSQL
- 使用 migration 管理建表
- 所有金额使用 integer cents 存储
- 所有关键表保留 created_at / updated_at
- 所有时间统一以 UTC 存储

---

## 3. 产品定位

本项目是一个 **Delivery Fulfillment SaaS**，核心能力为：

1. 接收配送请求
2. 创建配送单
3. 派单给司机 / 骑手
4. 管理取件、配送、签收
5. 记录轨迹、日志、异常
6. 处理 COD 代收款
7. 对外 webhook 回传状态
8. 支持多租户、多组织、多来源订单

---

## 4. 用户角色

统一使用以下角色：

- `admin`：平台管理员
- `tenant_admin`：租户管理员 / 商家老板
- `operator`：商家操作员 / 发单员
- `dispatcher`：调度员
- `driver`：司机 / 骑手

权限要求：

- admin 可查看全平台数据
- tenant_admin 仅可查看本租户数据
- operator 仅可管理本租户订单与基础信息
- dispatcher 可分配司机、处理异常、修改履约状态
- driver 只能处理分配给自己的配送单

---

## 5. 多租户设计

必须从第一天支持多租户。

### 核心实体

#### tenants
租户，代表一个商家、平台客户或企业客户。

#### organizations
租户下面的门店、仓库、自提点、配送站等。

#### users
统一用户表，用户属于某个租户，并带角色。

要求：

- 所有业务数据必须带 tenant_id
- 平台管理员除外，默认数据隔离
- 后台查询必须自动按 tenant_id 过滤

---

## 6. 业务域拆分

### A. 租户与权限域
- tenants
- organizations
- users
- api_keys
- access control

### B. 配送履约域
- deliveries
- delivery_logs
- delivery_tracking
- delivery_assignments
- proof_of_delivery

### C. 调度域
- driver availability
- dispatch actions
- reassignment
- dispatch rules

### D. 财务域
- delivery fees
- cod collections
- settlements
- payout records

### E. 平台集成域
- inbound api
- outbound webhook
- idempotency
- external refs
- callback logs

---

## 7. 配送单模型设计

### 交易订单与配送单分离
必须明确：

- 外部平台的“订单”不等于本平台内部的“配送单”
- 本平台只关心履约维度

### Delivery 核心字段
- tenant_id
- source_type（api / manual / platform / import / errand）
- source_platform
- source_order_no
- external_ref
- idempotency_key
- pickup info
- dropoff info
- goods info
- pricing info
- cod info
- status
- assigned_driver_id
- scheduled_at
- picked_up_at
- delivered_at
- completed_at
- failed_at
- cancelled_at

### 配送状态
统一使用：

- `pending`
- `assigned`
- `driver_arriving_pickup`
- `picked_up`
- `in_transit`
- `arrived`
- `signed`
- `completed`
- `failed`
- `cancelled`

要求：

- 后端必须实现状态机校验
- 不允许前端任意跳状态
- 每次状态变更都必须记录日志

---

## 8. COD 代收款设计

平台必须支持 COD，但财务逻辑必须清晰：

- 配送平台只负责“代为执行收款动作并回传结果”
- 交易归属和订单财务状态仍归来源平台 / 租户控制

### COD 字段要求
- cod_required
- cod_amount_cents
- cod_currency
- cod_status
- cod_collection_method
- cod_proof_required

### COD 状态
- `none`
- `pending`
- `collecting`
- `collected`
- `failed`
- `reconciled`
- `settled`

### COD 台账表
必须有单独表：
- expected amount
- collected amount
- method
- proof image
- collected_by_driver_id
- collected_at
- status
- notes

---

## 9. API-first 原则

必须按 API-first 开发，页面只是 API 的调用者。

### API version
全部接口使用：

- `/api/v1/...`

### 核心 API

#### Partner / Tenant APIs
- `POST /api/v1/deliveries` 创建配送单
- `GET /api/v1/deliveries/{id}` 查看配送单
- `GET /api/v1/deliveries` 配送单列表
- `POST /api/v1/deliveries/{id}/cancel` 取消配送单
- `GET /api/v1/deliveries/{id}/tracking` 轨迹

#### Dispatcher APIs
- `POST /api/v1/dispatch/assign`
- `POST /api/v1/dispatch/reassign`
- `POST /api/v1/dispatch/mark-failed`

#### Driver APIs
- `POST /api/v1/driver/deliveries/{id}/accept`
- `POST /api/v1/driver/deliveries/{id}/arrive-pickup`
- `POST /api/v1/driver/deliveries/{id}/pickup`
- `POST /api/v1/driver/deliveries/{id}/arrive-dropoff`
- `POST /api/v1/driver/deliveries/{id}/sign`
- `POST /api/v1/driver/deliveries/{id}/complete`
- `POST /api/v1/driver/deliveries/{id}/cod-collect`
- `POST /api/v1/driver/location`

#### Webhook
- `POST /api/v1/webhooks/delivery-status`

---

## 10. 幂等要求

创建配送单必须支持幂等，防止重复发单。

至少支持以下字段：

- source_order_no
- external_ref
- idempotency_key

规则：

- 同一租户下，同一幂等标识重复请求时，不得重复创建配送单
- 应直接返回已有结果

---

## 11. 数据库表（第一版必须完成）

### 必做表
- tenants
- organizations
- users
- api_keys
- deliveries
- delivery_logs
- delivery_tracking
- delivery_assignments
- proof_of_delivery
- cod_collections
- webhook_endpoints
- webhook_logs

### 建表规范
- 所有主键使用 bigserial
- 所有金额字段使用 integer cents
- 状态字段使用 varchar，并在应用层统一约束
- 关键索引必须补齐：tenant_id、status、assigned_driver_id、source_order_no、created_at

---

## 12. Web UI 需求

### 总体风格
- 现代、浅色、干净
- PC 和手机自动适配
- 手机版像 App
- 信息密度合理
- 统一卡片、表格、状态标签、按钮样式

### 公共设计要求
- 顶部导航 + 左侧菜单（PC）
- 手机端使用底部主导航或汉堡菜单
- 仪表盘卡片化展示
- 列表页支持搜索、筛选、状态标签、分页
- 表单分区清晰
- 状态流程可视化

### 设计语言
- 白底 / 浅灰底
- 蓝色系为主操作色
- 轻圆角、轻阴影
- 移动端点击区域足够大

---

## 13. 前端页面（第一版）

### A. 登录与身份
- 登录页
- 忘记密码（可先占位）

### B. 租户后台 / 商家端
- Dashboard
- 配送单列表
- 创建配送单页面
- 配送单详情页
- API Key 管理页
- Webhook 配置页
- 组织 / 门店管理页
- 用户管理页

### C. 调度端
- 调度台首页
- 待派单列表
- 已派单列表
- 异常单列表
- 派单弹窗 / 页面
- 改派功能

### D. 司机端（H5）
- 登录页
- 我的订单（待处理 / 进行中 / 已完成）
- 订单详情
- 接单
- 到达取件
- 确认取件
- 到达收货点
- 签收上传
- COD 收款页面
- 个人中心

### E. 平台后台
- 平台概览
- 租户管理
- 司机管理
- 全局订单监控
- 全局异常监控

---

## 14. 第一阶段 MVP 范围

Codex 第一阶段只做最核心可运行闭环：

### 后端
- 多租户基础
- 用户登录
- 配送单 CRUD（以创建 / 查看 / 列表为主）
- 派单
- 司机履约状态流转
- 日志记录
- Webhook 回调
- API Key 鉴权
- 幂等控制

### 前端
- 登录页
- Dashboard
- 商家端订单列表 / 详情 / 创建
- 调度端派单页面
- 司机端 H5 基础流程

### 暂不做复杂能力
- 智能调度
- 路线优化
- 高级计费
- 复杂报表
- 多语言（可预留）
- 消息中心（可预留）

---

## 15. 开发顺序要求

### Step 1
初始化项目骨架：
- PHP 8 项目结构
- 环境配置
- PostgreSQL 连接
- migration 系统
- 基础路由
- 基础认证

### Step 2
实现多租户与用户体系：
- tenants
- organizations
- users
- role middleware
- tenant scope

### Step 3
实现核心配送单模型：
- deliveries
- delivery_logs
- delivery_assignments
- list / detail / create

### Step 4
实现 API：
- partner API
- idempotency
- api key auth
- webhook 回调框架

### Step 5
实现调度与司机履约：
- assign / reassign
- accept
- pickup
- sign
- complete
- tracking upload

### Step 6
实现 UI：
- PC + Mobile 自适应
- Dashboard
- 列表页
- 详情页
- 司机端 H5

---

## 16. 目录结构要求

建议使用如下目录结构：

- `app/`
  - `Controllers/`
  - `Services/`
  - `Domain/`
  - `Repositories/`
  - `Middlewares/`
  - `Requests/`
  - `Policies/`
  - `Views/`
- `bootstrap/`
- `config/`
- `database/`
  - `migrations/`
  - `seeders/`
- `public/`
  - `assets/`
- `routes/`
- `storage/`
- `tests/`

要求：
- API 路由和 Web 路由分离
- Controller 尽量薄，业务逻辑放 Service
- Service 不直接输出 HTML
- Repository 负责数据库访问

---

## 17. 代码规范要求

- 所有代码使用 PHP 8 严格类型
- 所有关键流程写注释
- 所有输入做校验
- 所有状态流转统一走 service
- 所有错误返回结构统一
- 所有 API 返回 JSON
- 所有金额统一使用 cents
- 所有时间统一使用 UTC 存储，展示时可按租户时区转换

---

## 18. UI 交付标准

Codex 不仅要完成功能，还要完成完整 UI：

- 登录页面美观
- Dashboard 有统计卡片
- 列表页有搜索、筛选、状态标签
- 详情页层次清楚
- 表单页适合桌面与手机
- 司机端 H5 要大按钮、步骤清晰、适合单手操作

禁止：
- 粗糙后台风
- 无响应式页面
- 只有功能没有视觉层次
- 仅输出接口不做页面

---

## 19. 第一版交付结果

Codex 第一轮必须交付：

1. 完整项目骨架
2. PostgreSQL migrations
3. 登录与角色权限
4. 配送单核心流程
5. API 接口
6. Webhook 基础机制
7. 商家端 UI
8. 调度端 UI
9. 司机端 H5 UI
10. README + 安装部署说明

---

## 20. 给 Codex 的工作方式要求

Codex 需要：

- 每完成一个阶段，先总结已完成内容
- 再列出新增文件
- 再说明下一步计划
- 优先输出能运行的代码，而不是只写文档
- 不要空谈架构，必须落到文件、表、接口、页面

---

## 21. 给 Codex 的直接执行指令

请基于本说明，开始开发一个 **API-first、多租户、同城配送履约 SaaS 平台**。

严格使用：
- PHP 8
- PostgreSQL
- Web + Mobile 响应式 UI
- 商家端 + 调度端 + 司机端 H5

请先完成：

1. 项目初始化与目录结构
2. 数据库 migrations
3. 登录与权限
4. 核心 deliveries 模块
5. API 创建配送单
6. 派单与司机履约流程
7. 基础 UI 页面

每一步都直接产出代码，不要只给思路。