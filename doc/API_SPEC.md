# Delivery SaaS API 说明

## 1. 基本规范

- Base URL: `/api/v1`
- 数据格式：JSON
- 鉴权方式：
  - Partner API：`X-API-KEY` + `X-API-SECRET`
  - Web 登录用户：Session / Bearer Token（二选一，第一版可先 Session）
- 时间格式：ISO 8601
- 金额单位：integer cents
- 所有响应必须包含标准结构

### 成功响应示例
```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
失败响应示例
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "pickup.address": ["Pickup address is required"]
  }
}


2. 幂等规则

创建配送单时必须支持幂等。

支持字段：

idempotency_key
source_order_no
external_ref

规则：

同一 tenant 下，如果 idempotency_key 已存在，则直接返回已有配送单，不重复创建
3. 配送状态枚举
pending
assigned
driver_arriving_pickup
picked_up
in_transit
arrived
signed
completed
failed
cancelled
4. COD 状态枚举
none
pending
collecting
collected
failed
reconciled
settled
5. Partner API
5.1 创建配送单

POST /api/v1/deliveries

Headers
X-API-KEY
X-API-SECRET
Request Body
{
  "idempotency_key": "gb-20260416-0001",
  "source_type": "platform",
  "source_platform": "groupbuy_platform",
  "source_order_no": "GB202604160001",
  "external_ref": "ORDER-1001",
  "pickup": {
    "name": "Sender A",
    "phone": "1234567890",
    "address": "7995 Westminster Highway, Richmond, BC",
    "lat": 49.1701,
    "lng": -123.1368
  },
  "dropoff": {
    "name": "Receiver B",
    "phone": "6040000000",
    "address": "Burnaby BC",
    "lat": 49.2488,
    "lng": -122.9805
  },
  "goods": {
    "type": "seafood",
    "weight": 2.5,
    "note": "Keep cold"
  },
  "pricing": {
    "delivery_fee_cents": 1200
  },
  "cod": {
    "required": true,
    "amount_cents": 8000,
    "currency": "CAD",
    "collection_method": "cash",
    "proof_required": true
  },
  "scheduled_at": "2026-04-16T18:00:00Z"
}
Response
{
  "success": true,
  "message": "Delivery created",
  "data": {
    "id": 1001,
    "status": "pending",
    "source_order_no": "GB202604160001"
  }
}
5.2 配送单列表

GET /api/v1/deliveries

Query Params
status
source_order_no
assigned_driver_id
date_from
date_to
page
per_page
Response
{
  "success": true,
  "message": "OK",
  "data": {
    "items": [],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 0
    }
  }
}
5.3 配送单详情

GET /api/v1/deliveries/{id}

Response
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 1001,
    "status": "assigned",
    "pickup": {},
    "dropoff": {},
    "goods": {},
    "cod": {},
    "driver": {}
  }
}
5.4 取消配送单

POST /api/v1/deliveries/{id}/cancel

Request Body
{
  "reason": "Customer requested cancellation"
}
Response
{
  "success": true,
  "message": "Delivery cancelled",
  "data": {
    "id": 1001,
    "status": "cancelled"
  }
}
5.5 查询轨迹

GET /api/v1/deliveries/{id}/tracking

Response
{
  "success": true,
  "message": "OK",
  "data": {
    "items": [
      {
        "lat": 49.17,
        "lng": -123.13,
        "created_at": "2026-04-16T18:15:00Z"
      }
    ]
  }
}
6. Dispatcher API
6.1 派单

POST /api/v1/dispatch/assign

Request Body
{
  "delivery_id": 1001,
  "driver_id": 88,
  "note": "Nearest available driver"
}
6.2 改派

POST /api/v1/dispatch/reassign

Request Body
{
  "delivery_id": 1001,
  "driver_id": 89,
  "reason": "Driver unavailable"
}
6.3 标记失败

POST /api/v1/dispatch/mark-failed

Request Body
{
  "delivery_id": 1001,
  "reason": "Pickup failed"
}
7. Driver API
7.1 接单

POST /api/v1/driver/deliveries/{id}/accept

Response
{
  "success": true,
  "message": "Accepted",
  "data": {
    "id": 1001,
    "status": "assigned"
  }
}
7.2 到达取件地

POST /api/v1/driver/deliveries/{id}/arrive-pickup

7.3 确认取件

POST /api/v1/driver/deliveries/{id}/pickup

Request Body
{
  "note": "Picked up successfully"
}
7.4 到达收货地

POST /api/v1/driver/deliveries/{id}/arrive-dropoff

7.5 签收

POST /api/v1/driver/deliveries/{id}/sign

Request Body
{
  "receiver_name": "Tom",
  "note": "Signed by customer",
  "proof_image": "/uploads/signatures/1001.jpg"
}
7.6 完成

POST /api/v1/driver/deliveries/{id}/complete

7.7 COD 收款

POST /api/v1/driver/deliveries/{id}/cod-collect

Request Body
{
  "expected_amount_cents": 8000,
  "collected_amount_cents": 8000,
  "method": "cash",
  "proof_image": "/uploads/cod/1001.jpg",
  "note": "Collected full amount"
}
7.8 上传位置

POST /api/v1/driver/location

Request Body
{
  "delivery_id": 1001,
  "lat": 49.171,
  "lng": -123.132
}
8. Webhook
8.1 回调地址配置

租户可在后台配置一个或多个 webhook endpoint。

8.2 回调事件

第一版至少支持：

delivery.created
delivery.assigned
delivery.picked_up
delivery.in_transit
delivery.signed
delivery.completed
delivery.failed
delivery.cancelled
delivery.cod_collected
8.3 回调 payload 示例
{
  "event": "delivery.completed",
  "tenant_id": 1,
  "delivery": {
    "id": 1001,
    "source_order_no": "GB202604160001",
    "status": "completed",
    "assigned_driver_id": 88,
    "picked_up_at": "2026-04-16T18:30:00Z",
    "completed_at": "2026-04-16T19:10:00Z"
  },
  "timestamp": "2026-04-16T19:10:00Z"
}
9. 状态机规则
合法状态流转
pending -> assigned
assigned -> driver_arriving_pickup
driver_arriving_pickup -> picked_up
picked_up -> in_transit
in_transit -> arrived
arrived -> signed
signed -> completed
异常流转
pending -> cancelled
assigned -> cancelled
driver_arriving_pickup -> failed
picked_up -> failed
in_transit -> failed

要求：

所有非法跳转必须拒绝
所有状态变更必须写入 delivery_logs
10. 安全与校验
所有 Partner API 必须校验 api_key / api_secret
所有 Driver API 必须校验当前司机身份
所有 Dispatcher API 必须校验 tenant scope
所有输入做 validation
所有错误响应结构统一