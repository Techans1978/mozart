export class BaseCreateMenuProvider {
  /**
   *
   * @param injector
   * @param config
   */
  constructor(injector: import("didi").Injector, config: Config);

  getPopupMenuEntries(): {};
}
export type Config = {
    resourceType: string;
    className: string;
    groupName: string;
    createElement: Function;
    search?: string;
};
