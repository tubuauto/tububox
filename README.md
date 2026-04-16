# Delivery SaaS

一个 **可独立运营、支持多商家、多来源订单、API 接入的同城配送履约 SaaS 平台**。

## 项目定位

本项目不是某个商城附属的配送模块，而是一个独立的第三方配送履约平台，支持：

- 团购平台推单
- 商家后台发单
- 第三方 API 接单
- 手工创建配送单
- 调度派单
- 司机履约
- Webhook 状态回传
- COD 代收款

---

## 核心能力

- 多租户 SaaS
- API-first
- PHP 8 + PostgreSQL
- 商家端后台
- 调度端后台
- 司机端 H5
- Web + Mobile 响应式 UI

---

## 主要角色

- admin：平台管理员
- tenant_admin：租户管理员
- operator：操作员 / 发单员
- dispatcher：调度员
- driver：司机 / 骑手

---

## 第一阶段 MVP 范围

### 后端
- 多租户基础
- 登录与权限
- deliveries 核心模型
- API 创建配送单
- 派单 / 改派
- 司机接单 / 取件 / 签收 / 完成
- 轨迹上传
- webhook 回调
- COD 基础流程

### 前端
- 登录页
- Dashboard
- 商家端配送单列表 / 详情 / 创建页
- 调度端派单页
- 司机端 H5 基础流程

---

## 目录建议

```text
app/
  Controllers/
  Services/
  Domain/
  Repositories/
  Middlewares/
  Requests/
  Policies/
  Views/
bootstrap/
config/
database/
  migrations/
  seeders/
public/
  assets/
routes/
storage/
tests/
docs/
  MASTER_SPEC.md
  API_SPEC.md
  DB_SCHEMA.sql