import type { RouteRecordStringComponent } from '#/core/shared/types';

/**
 * 插件路由定义类型：component 可选，无 component 但有 children 时自动渲染为 RouterView 容器
 */
type PluginRoute = Omit<
  RouteRecordStringComponent,
  'children' | 'component'
> & {
  children?: PluginRoute[];
  component?: string;
};

/**
 * demo 插件前端静态路由定义。
 *
 * 菜单与路由已全部由后端 sys_menu（source=plugin:demo，code 以 `demo` 开头）下发，
 * 此处不再定义静态路由：开发环境下 scanner 会将插件静态路由合并进后端菜单，
 * 若静态路由 name（如 DemoRoot）与后端下发的 name（取自菜单 code，如 demo）不一致，
 * 合并去重失效会导致侧边栏重复渲染同一组菜单。
 */
const routes: PluginRoute[] = [];

export default routes;
