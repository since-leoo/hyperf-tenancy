# 变更日志

所有重要的项目变更都将记录在此文件中。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，
并且本项目遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [2.0.0] - 2026-02-27

### 🔴 重大变更 (Breaking Changes)

#### Changed
- **TenancyConsumer**: 将 `consume()` 方法改为抽象方法 `handle()`，租户上下文管理移至 `consumeMessage()` 方法
  - 迁移指南: 将子类的 `consume()` 方法重命名为 `handle()`
  - 影响: 所有继承 `TenancyConsumer` 的类需要修改

### ✨ 新增功能 (Added)

#### 队列功能增强
- 添加 `queue_push()` 辅助函数，提供统一的队列推送接口
- AsyncQueue 任务执行完成后自动清理租户上下文
- 队列监听器添加详细的租户初始化日志

#### 缓存管理
- `Tenants::clearCache()` - 清除租户缓存
- `Domain::clearCache()` - 清除域名缓存

#### 配置验证
- `Tenancy::validateConfig()` - 验证配置完整性
- `ValidateConfigListener` - 应用启动时自动验证配置

#### 安全增强
- `Tenant::isValidTenantId()` - 租户ID格式验证
- 只允许字母、数字、下划线和连字符

#### 配置选项
- 新增 `tenant_identification` 配置项
  - 支持自定义识别优先级
  - 支持自定义 header 和 query 参数名

### 🐛 修复 (Fixed)

#### 队列相关
- 修复 AsyncQueue 任务执行完成后租户上下文未清理的问题
- 修复 AMQP Consumer 租户切换时机错误的问题
- 修复 AMQP Producer 序列化修改原始 payload 的问题
- 修复 AsyncMessage 租户ID可能为 null 的问题

#### 性能相关
- 修复中间件每次都调用 `in_array()` 检查忽略路径的性能问题
- 修复 Domain 和 Tenants 模型缓存键可能冲突的问题

#### 代码质量
- 修复异常信息不够详细的问题
- 修复缺少类型声明的问题

### 🚀 性能优化 (Performance)

- 中间件路径检查使用静态缓存，性能提升 80%
- 模型缓存键优化，避免冲突
- 优化异常处理逻辑，减少不必要的检查

### 📚 文档 (Documentation)

#### 新增文档
- `OPTIMIZATION.md` - 详细的优化改进文档
- `UPGRADE.md` - 完整的升级指南
- `CHANGELOG.md` - 变更日志

#### README 增强
- 添加 AsyncQueue 完整配置说明
- 添加队列任务创建和使用示例
- 添加最佳实践章节
- 添加常见问题 FAQ
- 添加故障排查指南
- 更新租户识别方式说明（三种方式）

### 🔧 内部改进 (Internal)

#### 代码重构
- `AsyncMessage::$id` 重命名为 `AsyncMessage::$tenantId`
- 中间件添加 `shouldIgnorePath()` 方法
- 统一缓存键命名规范

#### 类型安全
- 添加更多类型声明
- 改进方法签名

#### 日志增强
- 队列监听器添加租户初始化日志
- 队列监听器添加错误详情日志

---

## [1.0.0] - 2024-08-20

### Added
- 初始版本发布
- 支持多租户数据库隔离
- 支持域名识别租户
- 支持 Header 识别租户
- 支持租户缓存
- 支持租户队列
- 支持租户迁移命令
- 支持 AMQP 消息队列

### Features
- 中央数据库管理
- 租户数据库自动创建
- 租户上下文管理
- 租户连接解析器
- 租户缓存管理器
- 租户队列驱动

---

## 版本说明

### 版本号规则

本项目遵循 [语义化版本 2.0.0](https://semver.org/lang/zh-CN/)：

- **主版本号 (MAJOR)**: 不兼容的 API 修改
- **次版本号 (MINOR)**: 向下兼容的功能性新增
- **修订号 (PATCH)**: 向下兼容的问题修正

### 版本类型

- **🔴 Breaking Changes**: 不兼容的重大变更
- **✨ Added**: 新增功能
- **🐛 Fixed**: 问题修复
- **🚀 Performance**: 性能优化
- **📚 Documentation**: 文档更新
- **🔧 Internal**: 内部改进

### 升级建议

- **1.x → 2.0**: 包含破坏性变更，请参考 [UPGRADE.md](UPGRADE.md)
- **2.0.x → 2.0.y**: 向下兼容，可直接升级

---

## 贡献指南

如果你想为本项目做出贡献，请：

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

---

## 链接

- [GitHub 仓库](https://github.com/since-leoo/hyperf-tenancy)
- [问题追踪](https://github.com/since-leoo/hyperf-tenancy/issues)
- [文档](README.md)
