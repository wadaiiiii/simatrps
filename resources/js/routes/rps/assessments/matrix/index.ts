import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:122
 * @route '/rps/{rps}/assessments/{assessment}/matrix'
 */
export const update = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/assessments/{assessment}/matrix',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:122
 * @route '/rps/{rps}/assessments/{assessment}/matrix'
 */
update.url = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    assessment: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                assessment: args.assessment,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{assessment}', parsedArgs.assessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:122
 * @route '/rps/{rps}/assessments/{assessment}/matrix'
 */
update.put = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:122
 * @route '/rps/{rps}/assessments/{assessment}/matrix'
 */
    const updateForm = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAssessmentController::update
 * @see app/Http/Controllers/RpsAssessmentController.php:122
 * @route '/rps/{rps}/assessments/{assessment}/matrix'
 */
        updateForm.put = (args: { rps: string | number, assessment: string | number } | [rps: string | number, assessment: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const matrix = {
    update: Object.assign(update, update),
}

export default matrix