export class BaseReplaceMenuProvider {
  /**
   *
   * @param injector
   * @param config
   */
  constructor(injector: import("didi").Injector, config: Config);

  /**
   * @param element
   *
   * @return
   */
  getPopupMenuEntries(element: PopupMenuTarget): PopupMenuEntries;
}
export type Config = {
    resourceType: string;
    className: string;
    groupName: string;
    replaceElement: Function;
    search?: string;
};
