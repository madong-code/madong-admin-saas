import type { RouteRecordStringComponent } from '@vben/types';

import { $t } from '#/locales';

/** 插件路由定义类型：component 可选，无 component 但有 children 时自动渲染为 RouterView 容器 */
type PluginRoute = Omit<
  RouteRecordStringComponent,
  'children' | 'component'
> & {
  children?: PluginRoute[];
  component?: string;
};

const routes: PluginRoute[] = [
  {
    path: '/demo',
    name: 'DemoRoot',
    component: 'BasicLayout',
    redirect: '/demo/crud/basic',
    meta: {
      title: $t('demo.menu.title'),
      icon: 'lucide:flask-conical',
      order: 9999,
      module: 'demo',
    },
    children: [
      // ===================== CRUD 示例 =====================
      {
        path: '/demo/crud',
        name: 'DemoCrudGroup',
        redirect: '/demo/crud/basic',
        meta: {
          title: $t('demo.menu.crud'),
          icon: 'lucide:database',
          module: 'demo',
        },
        children: [
          {
            path: '/demo/crud/basic',
            name: 'DemoCrudBasic',
            component: '/crud/basic',
            meta: {
              title: $t('demo.crud.basic.title'),
              icon: 'lucide:file-spreadsheet',
              module: 'demo',
            },
          },
          {
            path: '/demo/crud/tree',
            name: 'DemoCrudTree',
            component: 'crud/tree',
            meta: {
              title: $t('demo.crud.tree.title'),
              icon: 'lucide:git-fork',
              module: 'demo',
            },
          },
          {
            path: '/demo/crud/drawer',
            name: 'DemoCrudDrawer',
            component: 'crud/drawer',
            meta: {
              title: $t('demo.crud.drawer.title'),
              icon: 'lucide:panel-right-open',
              module: 'demo',
            },
          },
        ],
      },
      // ===================== 表单示例 =====================
      {
        path: '/demo/form',
        name: 'DemoFormGroup',
        redirect: '/demo/form/overview',
        meta: {
          title: $t('demo.menu.form'),
          icon: 'lucide:clipboard-list',
          module: 'demo',
        },
        children: [
          {
            path: '/demo/form/overview',
            name: 'DemoFormOverview',
            component: 'form/overview',
            meta: {
              title: $t('demo.form.overview.title'),
              icon: 'lucide:layout-dashboard',
              module: 'demo',
            },
          },
          {
            path: '/demo/form/basic',
            name: 'DemoFormBasic',
            component: 'form/basic',
            meta: {
              title: $t('demo.form.basic.title'),
              icon: 'lucide:file-text',
              module: 'demo',
            },
          },
          {
            path: '/demo/form/query',
            name: 'DemoFormQuery',
            component: 'form/query',
            meta: {
              title: $t('demo.form.query.title'),
              icon: 'lucide:search',
              module: 'demo',
            },
          },
          {
            path: '/demo/form/rules',
            name: 'DemoFormRules',
            component: 'form/rules',
            meta: {
              title: $t('demo.form.rules.title'),
              icon: 'lucide:shield-check',
              module: 'demo',
            },
          },
          {
            path: '/demo/form/dynamic',
            name: 'DemoFormDynamic',
            component: 'form/dynamic',
            meta: {
              title: $t('demo.form.dynamic.title'),
              icon: 'lucide:git-branch',
              module: 'demo',
            },
          },
          {
            path: '/demo/form/api',
            name: 'DemoFormApi',
            component: 'form/api',
            meta: {
              title: $t('demo.form.api.title'),
              icon: 'lucide:code-2',
              module: 'demo',
            },
          },
          {
            path: '/demo/form/merge',
            name: 'DemoFormMerge',
            component: 'form/merge',
            meta: {
              title: $t('demo.form.merge.title'),
              icon: 'lucide:combine',
              module: 'demo',
            },
          },
          {
            path: '/demo/form/custom-layout',
            name: 'DemoFormCustomLayout',
            component: 'form/custom-layout',
            meta: {
              title: $t('demo.form.custom_layout.title'),
              icon: 'lucide:panels-top-left',
              module: 'demo',
            },
          },
        ],
      },
      // ===================== Vxe 表格 =====================
      {
        path: '/demo/vxe-table',
        name: 'DemoVxeTableGroup',
        redirect: '/demo/vxe-table/basic',
        meta: {
          title: $t('demo.menu.vxe_table'),
          icon: 'lucide:sheet',
          module: 'demo',
        },
        children: [
          {
            path: '/demo/vxe-table/basic',
            name: 'DemoVxeTableBasic',
            component: 'vxe-table/basic',
            meta: {
              title: $t('demo.vxe_table.basic.title'),
              icon: 'lucide:table',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/remote',
            name: 'DemoVxeTableRemote',
            component: 'vxe-table/remote',
            meta: {
              title: $t('demo.vxe_table.remote.title'),
              icon: 'lucide:cloud-download',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/tree',
            name: 'DemoVxeTableTree',
            component: 'vxe-table/tree',
            meta: {
              title: $t('demo.vxe_table.tree.title'),
              icon: 'lucide:list-tree',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/custom-cell',
            name: 'DemoVxeTableCustomCell',
            component: 'vxe-table/custom-cell',
            meta: {
              title: $t('demo.vxe_table.custom_cell.title'),
              icon: 'lucide:square-stack',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/edit-cell',
            name: 'DemoVxeTableEditCell',
            component: 'vxe-table/edit-cell',
            meta: {
              title: $t('demo.vxe_table.edit_cell.title'),
              icon: 'lucide:pencil-line',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/edit-row',
            name: 'DemoVxeTableEditRow',
            component: 'vxe-table/edit-row',
            meta: {
              title: $t('demo.vxe_table.edit_row.title'),
              icon: 'lucide:edit-3',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/fixed',
            name: 'DemoVxeTableFixed',
            component: 'vxe-table/fixed',
            meta: {
              title: $t('demo.vxe_table.fixed.title'),
              icon: 'lucide:pin',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/form',
            name: 'DemoVxeTableForm',
            component: 'vxe-table/form',
            meta: {
              title: $t('demo.vxe_table.form.title'),
              icon: 'lucide:table-properties',
              module: 'demo',
            },
          },
          {
            path: '/demo/vxe-table/virtual',
            name: 'DemoVxeTableVirtual',
            component: 'vxe-table/virtual',
            meta: {
              title: $t('demo.vxe_table.virtual.title'),
              icon: 'lucide:zap',
              module: 'demo',
            },
          },
        ],
      },
      // ===================== 控件中心 =====================
      {
        path: '/demo/widgets',
        name: 'DemoWidgetCenter',
        redirect: '/demo/widgets/form-builder',
        meta: {
          title: $t('demo.menu.widget_center'),
          icon: 'lucide:blocks',
          module: 'demo',
        },
        children: [
          // 表单设计
          {
            path: '/demo/widgets/form-builder',
            name: 'DemoFormBuilder',
            component: 'widgets/form-builder/builder/index',
            meta: {
              title: $t('demo.widgets.form-builder.title'),
              icon: 'lucide:layout-template',
              module: 'demo',
            },
          },
          // 图片选择器
          {
            path: '/demo/widgets/image-selector',
            name: 'DemoImageSelector',
            component: 'widgets/image-selector/index',
            meta: {
              title: $t('demo.widgets.image-selector.title'),
              icon: 'lucide:image',
              module: 'demo',
            },
          },
          // 模态框
          {
            path: '/demo/widgets/modal',
            name: 'DemoModal',
            component: 'widgets/modal/index',
            meta: {
              title: $t('demo.widgets.modal.title'),
              icon: 'lucide:app-window',
              keepAlive: true,
              module: 'demo',
            },
          },
          // 抽屉
          {
            path: '/demo/widgets/drawer',
            name: 'DemoDrawer',
            component: 'widgets/drawer/index',
            meta: {
              title: $t('demo.widgets.drawer.title'),
              icon: 'lucide:panel-right',
              keepAlive: true,
              module: 'demo',
            },
          },
          // 验证码
          {
            path: '/demo/widgets/captcha',
            name: 'DemoCaptcha',
            component: 'widgets/captcha/slider-captcha',
            meta: {
              title: $t('demo.widgets.captcha.title'),
              icon: 'lucide:shield',
              module: 'demo',
            },
          },
          // 文本省略
          {
            path: '/demo/widgets/ellipsis',
            name: 'DemoEllipsis',
            component: 'widgets/ellipsis/index',
            meta: {
              title: $t('demo.widgets.ellipsis.title'),
              icon: 'lucide:more-horizontal',
              module: 'demo',
            },
          },
          // JSON 查看器
          {
            path: '/demo/widgets/json-viewer',
            name: 'DemoJsonViewer',
            component: 'widgets/json-viewer/index',
            meta: {
              title: $t('demo.widgets.json-viewer.title'),
              icon: 'lucide:braces',
              module: 'demo',
            },
          },
          // 加载
          {
            path: '/demo/widgets/loading',
            name: 'DemoLoading',
            component: 'widgets/loading/index',
            meta: {
              title: $t('demo.widgets.loading.title'),
              icon: 'lucide:loader-circle',
              module: 'demo',
            },
          },
          // 数字动画
          {
            path: '/demo/widgets/count-to',
            name: 'DemoCountTo',
            component: 'widgets/count-to/index',
            meta: {
              title: $t('demo.widgets.count-to.title'),
              icon: 'lucide:hash',
              module: 'demo',
            },
          },
          // 图片裁剪
          {
            path: '/demo/widgets/cropper',
            name: 'DemoCropper',
            component: 'widgets/cropper/index',
            meta: {
              title: $t('demo.widgets.cropper.title'),
              icon: 'lucide:crop',
              module: 'demo',
            },
          },
          // 右键菜单
          {
            path: '/demo/widgets/context-menu',
            name: 'DemoContextMenu',
            component: 'widgets/context-menu/index',
            meta: {
              title: $t('demo.widgets.context-menu.title'),
              icon: 'lucide:mouse-pointer-click',
              module: 'demo',
            },
          },
          // 尺寸监听
          {
            path: '/demo/widgets/resize',
            name: 'DemoResize',
            component: 'widgets/resize/basic',
            meta: {
              title: $t('demo.widgets.resize.title'),
              icon: 'lucide:maximize-2',
              module: 'demo',
            },
          },
          // Tippy 提示
          {
            path: '/demo/widgets/tippy',
            name: 'DemoTippy',
            component: 'widgets/tippy/index',
            meta: {
              title: $t('demo.widgets.tippy.title'),
              icon: 'lucide:message-square',
              module: 'demo',
            },
          },
          // 富文本编辑器
          {
            path: '/demo/widgets/tiptap',
            name: 'DemoTiptap',
            component: 'widgets/tiptap/index',
            meta: {
              title: $t('demo.widgets.tiptap.title'),
              icon: 'lucide:pen-tool',
              module: 'demo',
            },
          },
          // 动画
          {
            path: '/demo/widgets/motion',
            name: 'DemoMotion',
            component: 'widgets/motion/index',
            meta: {
              title: $t('demo.widgets.motion.title'),
              icon: 'lucide:wand-2',
              module: 'demo',
            },
          },
          // 国际化
          {
            path: '/demo/widgets/locale',
            name: 'DemoLocale',
            component: 'widgets/locale/index',
            meta: {
              title: $t('demo.widgets.locale.title'),
              icon: 'lucide:languages',
              module: 'demo',
            },
          },
        ],
      },
    ],
  },
];

export default routes;
