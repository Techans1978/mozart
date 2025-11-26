export class BaseAppendMenuProvider {
  /**
   *
   * @param injector
   * @param config
   */
  constructor(injector: import("didi").Injector, config: Config);

  /** @returns */
  getPopupMenuEntries(element: any): import("diagram-js/lib/features/popup-menu/PopupMenuProvider").PopupMenuEntries;
}
export type Config = {
    resourceType: string;
    className: string;
    groupName: string;
    createElement: Function;
    search?: string;
};
