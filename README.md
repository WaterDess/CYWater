# CYWater · 官网原型 / Website Prototype

一个**双语（中/英）、简约高级、功能完整**的协会官网原型。参考 AGU（American Geophysical Union）的设计语言：克制配色、编辑级排版、充足留白、图文驱动。

> ⚠️ **原型说明**：支付、会员账户、报名、订阅等均为前端模拟（mock），不会发生真实交易。表单提交后弹出 Toast 提示，无后端。

---

## ✨ 核心特性

- **中英双语一键切换** — 全站文案集中管理（`assets/js/i18n.js`），切换后自动联动导航、页脚、正文、表单。
- **简约高级设计系统** — 墨蓝 + 深青 + 米纸三色体系，Fraunces 衬线展示字 + Inter 无衬线正文（中文 Noto Serif/Sans SC）。
- **响应式** — 桌面 / 平板 / 手机三档断点，移动端抽屉式导航。
- **可访问性** — 语义化 HTML、`aria` 属性、键盘可操作、`prefers-reduced-motion` 支持。
- **共享布局** — 导航与页脚由 `assets/js/layout.js` 统一注入，所有页面同步、零重复维护。
- **原型交互** — 滚动揭示动画、Toast 通知、多步活动报名模态、FAQ 折叠、章程目录滚动定位、日程选项卡。

---

## 📁 文件结构

```
CYWater/
├── index.html                  # 首页（Hero / 数据 / 使命 / 四支柱 / 最新 / CTA）
├── about/
│   ├── index.html              # 关于（故事 / 价值观 / 时间线 / 治理）
│   ├── board.html              # 理事会（执委 / 董事会 / 顾问）
│   └── bylaws.html             # 章程（8 条 + 目录滚动定位）
├── membership/
│   ├── index.html              # 会员（权益 / 三档会费 / FAQ）
│   └── dashboard.html          # 会员后台（状态 / 数据 / 活动 / 收据表）
├── events/
│   ├── index.html              # 活动列表（筛选侧栏）
│   └── detail.html             # 活动详情（日程选项卡 / 嘉宾 / 多步报名模态）
├── news/
│   ├── index.html              # 新闻列表（头条 + 列表 + 热门侧栏）
│   └── article.html            # 文章详情
├── journal/
│   └── index.html              # 期刊（当期 + 投稿 + 往期归档表）
├── contact/
│   └── index.html              # 联系（信息 + 表单）
└── assets/
    ├── css/
    │   ├── base.css            # 设计令牌 / 重置 / 排版 / 基础工具
    │   ├── components.css       # 头部 / 页脚 / 按钮 / 卡片 / 表单 / 徽章
    │   └── pages.css           # 各页面专属样式 + 响应式断点
    ├── js/
    │   ├── i18n.js             # 双语字典 + 切换控制器（核心）
    │   ├── layout.js           # 头部 / 页脚 / 移动导航注入
    │   ├── main.js             # 滚动 / 抽屉 / reveal / toast / FAQ / 模态 / 选项卡
    │   └── register.js         # 多步报名流程
    └── img/
        ├── logo.svg            # 品牌主标识
        ├── favicon.svg         # 站点图标
        └── og.svg              # 社交分享卡
```

---

## 🚀 本地预览

无需构建。任选其一：

```bash
# 方式一：Python（任一台都有）
python -m http.server 8000
# 然后浏览器打开 http://localhost:8000

# 方式二：Node
npx serve .

# 方式三：直接双击 index.html 也可（部分浏览器对 fetch 本地文件有限制，推荐用前两种）
```

---

## 🌐 如何切换语言

- 点击右上角**地球图标按钮**（`EN` ↔ `中文`），全站即时切换。
- 首次访问会根据浏览器语言自动选择，选择会记在 `localStorage`，下次访问保持。

---

## 🎨 设计语言速览

| 角色     | 色值      | 用途                         |
| -------- | --------- | ---------------------------- |
| Ink      | `#0E1B2C` | 主文本、页头/页脚深色背景    |
| Teal     | `#0F766E` | 唯一强调色（按钮/链接/点缀） |
| Paper    | `#F6F3EC` | 暖米色正文背景               |
| Line     | `#E4E1D8` | 细发丝分隔线                 |
| Gold     | `#B68A35` | 赞助会员等克制的次强调       |

字体：**Fraunces**（展示标题）+ **Inter**（正文）+ **Noto Serif/Sans SC**（中文）。

---

## 🔧 二次开发指引

**新增页面**：
1. 复制任一现有页面（如 `about/index.html`）。
2. 改 `<html data-root="../">`（按目录深度调整）与 `<body data-page="xxx">`（用于导航高亮）。
3. 正文用 `data-i18n="组.键"` 标注需要翻译的文本，再到 `i18n.js` 补 `{ en, zh }`。

**新增翻译条目**：在 `assets/js/i18n.js` 的对应分组里加一行 `keyName: { en: "...", zh: "..." }`，然后页面里写 `data-i18n="组.keyName"`。

**改配色/字体**：全部集中在 `assets/css/base.css` 顶部的 `:root` 令牌区，改一处全站生效。

**接真实后端**：所有原型交互入口都是 `data-proto-form="类型"` 表单与 `data-modal-open` 按钮，到时把 `main.js` 里的 `submit` 拦截换成真实 `fetch` 即可。

---

## 📐 设计参考

- [AGU — American Geophysical Union](https://www.agu.org/)（结构、克制气质、图文驱动）
- [International Water Association](https://www.iwa-network.org/)
- [CUAHSI](https://www.cuahsi.org/)

---

## 📋 原型范围与边界

| 已实现（前端原型）        | 未实装（后续阶段）          |
| ------------------------- | --------------------------- |
| 全部页面与导航            | 真实支付（Stripe/支付宝/微信）|
| 中英双语切换              | 真实会员账户与登录          |
| 会员后台界面 + 收据表     | 真实数据持久化              |
| 多步活动报名流程          | 报名数据回传后端            |
| 联系/订阅/加入表单        | 邮件真实发送                |
| FAQ / 日程 / 目录定位     |                              |

---

## 🏷️ 版本与快照

当前 GitHub Pages（`gh-pages` 分支）展示的是 **原型阶段**，所有功能均为前端 mock。

原型阶段的最后稳定版本已打 tag：

```text
v0.1-prototype  →  commit e4b5a01
```

之后的提交进入**实装阶段**：移除占位内容（如 journal 改为 Coming soon）、用 CYWater 协会真实素材替换 mock 文案。

### 如何回到原型版本

```bash
# 只读查看（看完用 git checkout main 回来）
git checkout v0.1-prototype

# 在 GitHub 网页直接浏览该版本
# https://github.com/WaterDess/CYWater/tree/v0.1-prototype

# 对比原型与当前的差异
git diff v0.1-prototype main
```

---

*原型构建日期：2026-06-28 · 设计与开发：CYWater 原型小组*
