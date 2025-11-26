/**
 *
 * @param resource
 * @param bpmnFactory
 */
export function createElement(
  resource: CallActivity,
  bpmnFactory: import("bpmn-js/lib/features/modeling/BpmnFactory").default
): any;
export type CallActivity = {
    type: "bpmnProcess";
    name: string;
    processId: string;
};
